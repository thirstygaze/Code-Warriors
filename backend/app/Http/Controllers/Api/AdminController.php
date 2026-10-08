<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function report(): JsonResponse
    {
        $today = today();

        return response()->json([
            'orders_today' => Order::whereDate('order_date', $today)->count(),
            'sales_today' => (float) Order::whereDate('order_date', $today)->whereNotIn('status', ['cancelled'])->sum('total_amount'),
            'open_orders' => Order::whereIn('status', ['pending', 'preparing', 'ready'])->count(),
            'active_students' => User::where('role', 'student')->where('is_active', true)->count(),
            'active_staff' => User::whereIn('role', ['staff', 'admin'])->where('is_active', true)->count(),
            'available_menu_items' => MenuItem::where('availability', true)->count(),
            'sales_by_day' => Order::query()
                ->selectRaw('order_date, SUM(total_amount) as total_sales, COUNT(*) as order_count')
                ->whereBetween('order_date', [$today->copy()->subDays(6), $today])
                ->where('status', '!=', 'cancelled')
                ->groupBy('order_date')
                ->orderBy('order_date')
                ->get(),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->with(['studentProfile', 'staffProfile'])->orderBy('role')->orderBy('name')->paginate(50),
        ]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', Rule::in(['student', 'staff', 'admin'])],
            'student_number' => ['required_if:role,student', 'nullable', 'string', 'max:50', 'unique:students,student_number'],
            'grade_level' => ['nullable', 'string', 'max:40'],
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:staff,employee_number'],
            'position' => ['required_if:role,staff', 'nullable', 'string', 'max:100'],
            'stall_name' => ['required_if:role,staff', 'nullable', 'string', 'max:120'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'is_active' => true,
            ]);

            if ($validated['role'] === 'student') {
                Student::create([
                    'user_id' => $user->id,
                    'student_number' => $validated['student_number'],
                    'grade_level' => $validated['grade_level'] ?? null,
                ]);
            } elseif ($validated['role'] === 'staff') {
                Staff::create([
                    'user_id' => $user->id,
                    'employee_number' => $validated['employee_number'] ?? null,
                    'position' => $validated['position'],
                    'stall_name' => $validated['stall_name'],
                ]);
            }

            return $user;
        });

        return response()->json(['data' => $user->load(['studentProfile', 'staffProfile'])], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user) && (($request->has('is_active') && ! $request->boolean('is_active')) || $request->input('role', $user->role) !== 'admin')) {
            return response()->json(['message' => 'You cannot deactivate your own administrator account or change your own role.'], 422);
        }

        $newRole = $request->input('role', $user->role);
        $validated = $request->validate([
            'role' => ['sometimes', Rule::in(['student', 'staff', 'admin'])],
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:255'],
            'student_number' => [
                Rule::requiredIf(($request->has('role') || $request->hasAny(['student_number', 'grade_level'])) && $newRole === 'student' && ! $user->studentProfile),
                'nullable',
                'string',
                'max:50',
                Rule::unique('students', 'student_number')->ignore($user->id, 'user_id'),
            ],
            'grade_level' => ['sometimes', 'nullable', 'string', 'max:40'],
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('staff', 'employee_number')->ignore($user->id, 'user_id'),
            ],
            'position' => [
                Rule::requiredIf(($request->has('role') || $request->hasAny(['employee_number', 'position', 'stall_name'])) && $newRole === 'staff' && ! $user->staffProfile),
                'nullable',
                'string',
                'max:100',
            ],
            'stall_name' => [
                Rule::requiredIf(($request->has('role') || $request->hasAny(['employee_number', 'position', 'stall_name'])) && $newRole === 'staff' && blank($user->staffProfile?->stall_name)),
                'nullable',
                'string',
                'max:120',
            ],
        ]);

        DB::transaction(function () use ($request, $user, $validated, $newRole): void {
            $user->update(collect($validated)->only(['role', 'is_active', 'name'])->all());

            if ($newRole === 'student' && ($request->has('role') || $request->hasAny(['student_number', 'grade_level']))) {
                Student::updateOrCreate(
                    ['user_id' => $user->id],
                    collect($validated)->only(['student_number', 'grade_level'])->all(),
                );
            } elseif ($request->has('role') && $newRole !== 'student') {
                $user->studentProfile()->delete();
            }

            if ($newRole === 'staff' && ($request->has('role') || $request->hasAny(['employee_number', 'position', 'stall_name']))) {
                Staff::updateOrCreate(
                    ['user_id' => $user->id],
                    collect($validated)->only(['employee_number', 'position', 'stall_name'])->all(),
                );
            } elseif ($request->has('role') && $newRole !== 'staff') {
                $user->staffProfile()->delete();
            }
        });

        return response()->json(['data' => $user->fresh()->load(['studentProfile', 'staffProfile'])]);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'You cannot permanently delete your own administrator account.'], 422);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Account permanently deleted.']);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::withCount('menuItems')->orderBy('category_name')->get()]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:80', 'unique:categories,category_name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json(['data' => Category::create($validated)], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'category_name' => ['sometimes', 'string', 'max:80', Rule::unique('categories', 'category_name')->ignore($category->category_id, 'category_id')],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
        $category->update($validated);

        return response()->json(['data' => $category->fresh()]);
    }

    public function deleteCategory(Category $category): JsonResponse
    {
        if ($category->menuItems()->exists()) {
            return response()->json(['message' => 'Move or remove this category’s menu items before deleting it.'], 409);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}

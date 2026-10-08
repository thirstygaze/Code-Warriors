<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            'Meals' => 'Fresh, filling lunch bowls and hot meals.',
            'Snacks' => 'A little something to enjoy between classes.',
            'Drinks' => 'Cold and refreshing school-day drinks.',
            'Desserts' => 'A sweet finish to your lunch break.',
        ])->mapWithKeys(fn (string $description, string $name) => [
            $name => Category::firstOrCreate(
                ['category_name' => $name],
                ['description' => $description],
            ),
        ]);

        $items = [
            ['Crispy chicken rice', 'Golden chicken, garlic rice, and house sauce.', 'Meals', 65, 'photo-1569058242253-92a9c755a0ec', 24],
            ['Beef tapa bowl', 'Marinated beef, egg, and atchara.', 'Meals', 75, 'photo-1546833999-b9f581a1996d', 18],
            ['Creamy carbonara', 'Smoky bacon, cream sauce, and parmesan.', 'Meals', 60, 'photo-1473093295043-cdd812d0e601', 8],
            ['Fresh lumpia', 'Crunchy greens with peanut sauce.', 'Snacks', 35, 'photo-1540420773420-3366772f4999', 12],
            ['Iced matcha latte', 'Ceremonial matcha and fresh milk.', 'Drinks', 45, 'photo-1515823064-d6e0c04616a7', 31],
            ['Mango graham cup', 'Sweet mango, silky cream, and graham.', 'Desserts', 40, 'photo-1488477181946-6428a0291777', 6],
            ['Pork sisig rice bowl', 'Sizzling pork, egg, and steamed rice.', 'Meals', 70, 'photo-1512058564366-18510be2db19', 15],
            ['Veggie fried rice', 'Wok-fried rice with garden vegetables.', 'Meals', 50, 'photo-1603133872878-684f208fb84b', 20],
            ['Tuna sandwich', 'Creamy tuna, crisp lettuce, and wheat bread.', 'Snacks', 40, 'photo-1528735602780-2552fd46c7af', 11],
            ['Cheesy potato wedges', 'Oven-crisp potatoes with melted cheese.', 'Snacks', 35, 'photo-1573080496219-bb080dd4f877', 7],
            ['Calamansi cooler', 'Fresh-squeezed calamansi with a little honey.', 'Drinks', 25, 'photo-1513558161293-cdaf765edfd7', 26],
            ['Strawberry banana shake', 'Real strawberries, banana, and fresh milk.', 'Drinks', 45, 'photo-1505252585461-04db1eb84625', 14],
            ['Chocolate chip cookie', 'Soft-baked cookie with chocolate chunks.', 'Desserts', 25, 'photo-1499636136210-6f4ee915583e', 5],
            ['Pancit canton', 'Stir-fried noodles, vegetables, and calamansi.', 'Meals', 55, 'photo-1569718212165-3a8278d5f624', 17],
            ['Chicken adobo rice', 'Tender chicken adobo served with steamed rice.', 'Meals', 70, 'photo-1512058564366-18510be2db19', 16],
            ['Tocino egg rice', 'Sweet Filipino tocino with egg and garlic rice.', 'Meals', 65, 'photo-1546833999-b9f581a1996d', 14],
            ['Ham and cheese sandwich', 'Toasted bread filled with ham and melted cheese.', 'Snacks', 40, 'photo-1528735602780-2552fd46c7af', 12],
            ['Banana cue', 'Caramelized saba banana on a stick.', 'Snacks', 20, 'photo-1488477181946-6428a0291777', 18],
            ['Bottled water', 'Chilled bottled drinking water.', 'Drinks', 15, 'photo-1548839140-29a749e1cf4d', 30],
            ['Fudge brownie', 'Rich chocolate brownie baked for a sweet treat.', 'Desserts', 30, 'photo-1499636136210-6f4ee915583e', 10],
        ];

        foreach ($items as [$name, $description, $category, $price, $image, $quantity]) {
            $item = MenuItem::firstOrCreate(
                ['item_name' => $name],
                [
                    'category_id' => $categories[$category]->category_id,
                    'description' => $description,
                    'price' => $price,
                    'availability' => true,
                    'image_url' => "https://images.unsplash.com/{$image}?auto=format&fit=crop&w=680&q=82",
                ],
            );

            $item->inventory()->firstOrCreate(
                ['item_id' => $item->item_id],
                ['quantity' => $quantity, 'reorder_level' => 5],
            );
        }

        $adminEmail = config('smartbite.admin.email');
        $adminPassword = config('smartbite.admin.password');

        if ($adminEmail && $adminPassword) {
            if (strlen($adminPassword) < 12) {
                throw new \InvalidArgumentException('SMARTBITE_ADMIN_PASSWORD must contain at least 12 characters.');
            }

            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => config('smartbite.admin.name'),
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                    'is_active' => true,
                ],
            );
        }
    }
}

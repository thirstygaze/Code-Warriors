export type Category = "All" | (string & {});

export type AccountRole = "student" | "staff" | "admin";

export type Account = { id: number; role: AccountRole; name: string; email: string; staff_profile?: { stall_name: string | null } };

export type View = "Order food" | "My orders" | "Notifications" | "Staff dashboard" | "Admin dashboard" | "Orders" | "Menu" | "Inventory" | "Team" | "Categories";

export type Product = {
  id: number;
  name: string;
  detail: string;
  category: Exclude<Category, "All">;
  categoryId?: number;
  stallName?: string | null;
  price: number;
  image: string;
  stock?: number;
  reorderLevel?: number;
  listed?: boolean;
  tag?: string;
  available: boolean;
};

export type Order = {
  id: string;
  apiId?: number;
  items: string;
  total: number;
  time: string;
  status: "Pending" | "Preparing" | "Ready for pickup" | "Completed" | "Cancelled";
  paymentStatus?: "pending" | "paid" | "failed";
  paymentMethod?: "cash" | "paymongo" | null;
  checkoutUrl?: string | null;
  studentEmail?: string;
};

export type StudentNotification = { id: string; apiId?: number; studentEmail: string; orderId: string; message: string; time: string; read: boolean };

export type ManagedAccount = { id: number; name: string; email: string; role: AccountRole; is_active: boolean; staff_profile?: { stall_name: string | null } | null };

export type AdminReport = { orders_today: number; sales_today: number; open_orders: number; active_students: number; active_staff: number; available_menu_items: number; sales_by_day: { order_date: string; total_sales: number; order_count: number }[] };

export type ManagedCategory = { category_id: number; category_name: string; description?: string | null; menu_items_count?: number };

export type NewMenuItem = { category_id: number; item_name: string; description: string; price: number; quantity: number; image_url?: string };

export type UpdatedMenuItem = Omit<NewMenuItem, "quantity" | "image_url"> & { image_url: string | null };

export const pickupWindows = [
  { value: "8:00", label: "8:00 – 8:45 AM" },
  { value: "8:45", label: "8:45 – 9:30 AM" },
  { value: "9:30", label: "9:30 – 10:15 AM" },
  { value: "10:15", label: "10:15 – 10:30 AM" },
  { value: "10:30", label: "10:30 – 10:45 AM" },
  { value: "11:30", label: "11:30 – 11:45 AM" },
  { value: "11:45", label: "11:45 AM – 12:00 PM" },
  { value: "12:00", label: "12:00 – 12:15 PM" },
  { value: "12:15", label: "12:15 – 12:30 PM" },
  { value: "12:30", label: "12:30 – 12:45 PM" },
  { value: "12:45", label: "12:45 – 1:00 PM" },
    { value: "1:00", label: "1:45 – 2:00 PM" },
  { value: "1:45", label: "1:45 – 2:30 PM" },
  { value: "2:30", label: "2:30 – 3:15 PM" },
  { value: "3:15", label: "3:15 – 4:00 PM" },
  { value: "4:00", label: "4:00 – 4:45 PM" },
 { value: "4:45", label: "4:45 – 5:30 PM" },

];

export const initialProducts: Product[] = [
  { id: 1, name: "Crispy chicken rice", detail: "Golden chicken · garlic rice · house sauce", category: "Meals", price: 65, image: "photo-1569058242253-92a9c755a0ec", tag: "Bestseller", available: true },
  { id: 2, name: "Beef tapa bowl", detail: "Marinated beef · egg · atchara", category: "Meals", price: 75, image: "photo-1546833999-b9f581a1996d", tag: "Student pick", available: true },
  { id: 3, name: "Creamy carbonara", detail: "Smoky bacon · cream sauce · parmesan", category: "Meals", price: 60, image: "photo-1473093295043-cdd812d0e601", available: true },
  { id: 4, name: "Fresh lumpia", detail: "Crunchy greens · peanut sauce", category: "Snacks", price: 35, image: "photo-1540420773420-3366772f4999", tag: "Fresh today", available: true },
  { id: 5, name: "Iced matcha latte", detail: "Ceremonial matcha · fresh milk", category: "Drinks", price: 45, image: "photo-1515823064-d6e0c04616a7", available: true },
  { id: 6, name: "Mango graham cup", detail: "Sweet mango · silky cream · graham", category: "Desserts", price: 40, image: "photo-1488477181946-6428a0291777", tag: "Limited", available: true },
  { id: 7, name: "Pork sisig rice bowl", detail: "Sizzling pork · egg · steamed rice", category: "Meals", price: 70, image: "photo-1512058564366-18510be2db19", tag: "Campus favorite", available: true },
  { id: 8, name: "Veggie fried rice", detail: "Wok-fried rice · garden vegetables", category: "Meals", price: 50, image: "photo-1603133872878-684f208fb84b", tag: "Meat-free", available: true },
  { id: 9, name: "Tuna sandwich", detail: "Creamy tuna · crisp lettuce · wheat bread", category: "Snacks", price: 40, image: "photo-1528735602780-2552fd46c7af", available: true },
  { id: 10, name: "Cheesy potato wedges", detail: "Oven-crisp potatoes · melted cheese", category: "Snacks", price: 35, image: "photo-1573080496219-bb080dd4f877", tag: "Shareable", available: true },
  { id: 11, name: "Calamansi cooler", detail: "Fresh-squeezed calamansi · a little honey", category: "Drinks", price: 25, image: "photo-1513558161293-cdaf765edfd7", tag: "Fresh today", available: true },
  { id: 12, name: "Strawberry banana shake", detail: "Real strawberries · banana · fresh milk", category: "Drinks", price: 45, image: "photo-1505252585461-04db1eb84625", available: true },
  { id: 13, name: "Chocolate chip cookie", detail: "Soft-baked cookie · chocolate chunks", category: "Desserts", price: 25, image: "photo-1499636136210-6f4ee915583e", tag: "Baked today", available: true },
  { id: 14, name: "Pancit canton", detail: "Stir-fried noodles · vegetables · calamansi", category: "Meals", price: 55, image: "photo-1569718212165-3a8278d5f624", available: true },
  { id: 15, name: "Chicken adobo rice", detail: "Tender chicken adobo · steamed rice", category: "Meals", price: 70, image: "photo-1512058564366-18510be2db19", available: true },
  { id: 16, name: "Tocino egg rice", detail: "Sweet Filipino tocino · egg · garlic rice", category: "Meals", price: 65, image: "photo-1546833999-b9f581a1996d", available: true },
  { id: 17, name: "Ham and cheese sandwich", detail: "Toasted bread · ham · melted cheese", category: "Snacks", price: 40, image: "photo-1528735602780-2552fd46c7af", available: true },
  { id: 18, name: "Banana cue", detail: "Caramelized saba banana on a stick", category: "Snacks", price: 20, image: "photo-1488477181946-6428a0291777", available: true },
  { id: 19, name: "Bottled water", detail: "Chilled bottled drinking water", category: "Drinks", price: 15, image: "photo-1548839140-29a749e1cf4d", available: true },
  { id: 20, name: "Fudge brownie", detail: "Rich chocolate brownie baked for a sweet treat", category: "Desserts", price: 30, image: "photo-1499636136210-6f4ee915583e", available: true },
];

export const iconPaths: Record<string, string> = {
  grid: "M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z",
  bag: "M5 8h14l1 13H4L5 8zm4 0a3 3 0 0 1 6 0",
  receipt: "M5 3h14v18l-3-2-4 2-4-2-3 2V3zm4 5h6m-6 4h6m-6 4h3",
  menu: "M4 6h16M4 12h16M4 18h16",
  box: "m12 3 9 5-9 5-9-5 9-5zm-9 5v9l9 5 9-5V8m-9 5v9",
  search: "m20 20-4.5-4.5M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0z",
  bell: "M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12a2 2 0 0 0 4 0",
  users: "M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm7-7a4 4 0 0 1 0 8m2 3a4 4 0 0 1 3 4v2",
  logout: "M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6",
  plus: "M12 5v14m-7-7h14",
  minus: "M5 12h14",
  close: "m18 6-12 12M6 6l12 12",
  arrow: "M5 12h14m-7-7 7 7-7 7",
  clock: "M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z",
  check: "m5 12 4 4L19 6",
  heart: "M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8z",
  spark: "m12 3 1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2L12 3zm7 12 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15z",
  key: "M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.778-7.778zm0 0L15.5 7.5m0 0L18 10l3-3-2.5-2.5m-3 3L18 10",
};

export const money = (amount: number) => `₱${amount.toFixed(2)}`;

export const API_URL = (process.env.NEXT_PUBLIC_SMARTBITE_API_URL ?? "http://localhost:8000/api").replace(/\/$/, "");

export const TOKEN_KEY = "smartbite-api-token";

export const foodImage = (image: string, width: number, quality: number) => image.startsWith("http")
  ? image
  : `https://images.unsplash.com/${image}?auto=format&fit=crop&w=${width}&q=${quality}`;

export class ApiRequestError extends Error {
  constructor(message: string, readonly status: number) {
    super(message);
    this.name = "ApiRequestError";
  }
}

export async function apiRequest<T>(path: string, token?: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (options.body) headers.set("Content-Type", "application/json");
  if (token) headers.set("Authorization", `Bearer ${token}`);
  const response = await fetch(`${API_URL}${path}`, { ...options, headers });
  const payload = await response.json().catch(() => ({})) as T & { message?: string };
  if (!response.ok) throw new ApiRequestError(payload.message ?? `Smartbite API request failed (${response.status}).`, response.status);
  return payload;
}

export function orderStatus(status: string): Order["status"] {
  return status === "ready" ? "Ready for pickup" : status === "pending" ? "Pending" : status === "cancelled" ? "Cancelled" : status === "completed" ? "Completed" : "Preparing";
}

export function mapApiProduct(item: {
  item_id: number;
  item_name: string;
  description?: string | null;
  category_id?: number;
  price: string | number;
  availability: boolean;
  image_url?: string | null;
  category?: { category_name: string };
  staff?: { stall_name?: string | null } | null;
  inventory?: { quantity: number; reorder_level?: number };
}): Product {
  return {
    id: item.item_id,
    name: item.item_name,
    detail: item.description ?? "",
    category: item.category?.category_name ?? "Meals",
    categoryId: item.category_id,
    stallName: item.staff?.stall_name ?? null,
    price: Number(item.price),
    image: item.image_url ?? initialProducts.find((product) => product.name === item.item_name)?.image ?? "",
    stock: item.inventory?.quantity,
    reorderLevel: item.inventory?.reorder_level,
    listed: item.availability,
    available: item.availability && (item.inventory?.quantity ?? 1) > 0,
  };
}

export function mapApiNotification(notification: {
  notification_id: number;
  order_id?: number | null;
  message: string;
  is_read: boolean;
  created_at: string;
}, email: string): StudentNotification {
  return {
    id: String(notification.notification_id),
    apiId: notification.notification_id,
    studentEmail: email,
    orderId: notification.order_id ? `#SB-${notification.order_id}` : "Canteen update",
    message: notification.message,
    time: new Date(notification.created_at).toLocaleString("en-PH", { dateStyle: "medium", timeStyle: "short" }),
    read: notification.is_read,
  };
}

export function mapApiOrder(order: {
  order_id: number;
  total_amount: string | number;
  status: string;
  pickup_time: string;
  items?: { quantity: number; menu_item?: { item_name: string } }[];
  student?: { email?: string };
  payment?: { payment_provider?: "cash" | "paymongo"; payment_method?: string | null; payment_status: "pending" | "paid" | "failed"; checkout_url?: string | null };
}, studentEmail?: string): Order {
  return {
    id: `#SB-${order.order_id}`,
    apiId: order.order_id,
    items: order.items?.map((item) => `${item.menu_item?.item_name ?? "Menu item"} × ${item.quantity}`).join(", ") ?? "",
    total: Number(order.total_amount),
    time: order.pickup_time,
    status: orderStatus(order.status),
    paymentStatus: order.payment?.payment_status,
    paymentMethod: order.payment?.payment_provider ?? (order.payment?.payment_method === "cash" ? "cash" : "paymongo"),
    checkoutUrl: order.payment?.checkout_url,
    studentEmail: studentEmail ?? order.student?.email,
  };
}

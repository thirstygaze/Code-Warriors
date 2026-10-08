"use client";

import { useState, type CSSProperties, type FormEvent } from "react";
import { foodImage, money, type ManagedCategory, type NewMenuItem, type Order, type Product, type UpdatedMenuItem, type View } from "../lib/smartbite";
import { Icon, PageHeading } from "./SharedComponents";
import { OrderTable } from "./OrderTable";

export function Overview({ orders, products, onNavigate }: { orders: Order[]; products: Product[]; onNavigate: (view: View) => void }) {
  const preparing = orders.filter((order) => order.status === "Preparing").length;
  const awaiting = orders.filter((order) => order.status === "Ready for pickup").length;
  return <div className="management-content">
    <PageHeading kicker="YOUR CANTEEN TODAY" title="A fresh start, team." subtitle="Here’s what’s happening at your canteen today." action={<button className="primary-action" type="button" onClick={() => onNavigate("Orders")}><Icon name="receipt" size={16} /> Manage orders</button>} />
    <div className="metric-grid">
      {[{ label: "Open orders", value: String(orders.length), note: "In the current queue", tint: "mint", icon: "bag" }, { label: "Being prepared", value: String(preparing), note: "Kitchen working on them", tint: "peach", icon: "grid" }, { label: "Awaiting pickup", value: String(awaiting), note: "Ready for students", tint: "lavender", icon: "clock" }, { label: "Menu availability", value: `${products.filter((item) => item.available).length}/${products.length}`, note: "Items available today", tint: "butter", icon: "menu" }].map((metric) => <div className="metric-card" key={metric.label}><span className={`metric-icon ${metric.tint}`}><Icon name={metric.icon} size={18} /></span><span className="metric-label">{metric.label}</span><strong>{metric.value}</strong><small>{metric.note}</small></div>)}
    </div>
    <div className="management-columns">
      <section className="management-card recent-orders"><div className="card-heading"><div><h2>Recent orders</h2><p>A little snapshot of the lunch rush.</p></div><button type="button" onClick={() => onNavigate("Orders")}>View all <Icon name="arrow" size={14} /></button></div><OrderTable orders={orders.slice(0, 4)} /></section>
      <section className="management-card specials-card"><span className="section-eyebrow">YOUR MENU, AT A GLANCE</span><h2>Serving up smiles.</h2><p>Small changes make lunch a little better for everyone.</p><div className="specials-stat"><span>On the menu</span><strong>{products.filter((product) => product.available).length} <small>items</small></strong></div><div className="specials-stat"><span>Out of stock</span><strong>{products.filter((product) => !product.available).length} <small>items</small></strong></div><button type="button" onClick={() => onNavigate("Menu")}>Take a look at the menu <Icon name="arrow" size={15} /></button></section>
    </div>
  </div>;
}

export function OrdersView({ orders, onStatusChange, onCashReceived, onCancel }: { orders: Order[]; onStatusChange: (id: string) => void; onCashReceived: (id: string) => void; onCancel: (id: string) => void }) {
  return <div className="management-content"><PageHeading kicker="THE LUNCH RUSH" title="Orders" subtitle="Accept paid orders to start preparation. Reject unpaid pending orders; paid orders need a refund first." action={<span className="orders-live"><i /> LIVE ORDERS</span>} /><div className="orders-summary"><span className="summary-spark">✳</span><span><strong>Looking good, team!</strong><small>{orders.filter((order) => order.status === "Preparing").length} orders are being prepared right now.</small></span><span className="summary-total">{orders.length}<small> total orders</small></span></div><section className="management-card orders-card"><div className="card-heading"><div><h2>Today’s orders</h2><p>Updates as orders come in from students.</p></div><span className="today-label">TODAY</span></div><OrderTable orders={orders} onStatusChange={onStatusChange} onCashReceived={onCashReceived} onCancel={onCancel} /></section></div>;
}

export function MenuView({ products, categories, stallName, onToggle, onCreate, onEdit }: { products: Product[]; categories: ManagedCategory[]; stallName?: string | null; onToggle: (id: number) => void; onCreate: (item: NewMenuItem) => Promise<void>; onEdit: (id: number, item: UpdatedMenuItem) => Promise<void> }) {
  const [saving, setSaving] = useState(false);
  const [savingEdit, setSavingEdit] = useState(false);
  const [editingProductId, setEditingProductId] = useState<number | null>(null);
  const [error, setError] = useState("");
  const [editError, setEditError] = useState("");

  async function submitNewItem(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setSaving(true);
    const formElement = event.currentTarget;
    const formData = new FormData(formElement);
    const imageUrl = String(formData.get("image_url") ?? "").trim();
    try {
      await onCreate({
        category_id: Number(formData.get("category_id")),
        item_name: String(formData.get("item_name")).trim(),
        description: String(formData.get("description") ?? "").trim(),
        price: Number(formData.get("price")),
        quantity: Number(formData.get("quantity")),
        ...(imageUrl ? { image_url: imageUrl } : {}),
      });
      formElement.reset();
    } catch (createError) {
      setError(createError instanceof Error ? createError.message : "Could not add the menu item.");
    } finally {
      setSaving(false);
    }
  }

  async function submitEdit(event: FormEvent<HTMLFormElement>, productId: number) {
    event.preventDefault();
    setEditError("");
    setSavingEdit(true);
    const formData = new FormData(event.currentTarget);
    const imageUrl = String(formData.get("image_url") ?? "").trim();
    try {
      await onEdit(productId, {
        category_id: Number(formData.get("category_id")),
        item_name: String(formData.get("item_name")).trim(),
        description: String(formData.get("description") ?? "").trim(),
        price: Number(formData.get("price")),
        image_url: imageUrl || null,
      });
      setEditingProductId(null);
    } catch (editFailure) {
      setEditError(editFailure instanceof Error ? editFailure.message : "Could not update the menu item.");
    } finally {
      setSavingEdit(false);
    }
  }

  return <div className="management-content">
    <PageHeading kicker={stallName ?? "A LITTLE SOMETHING FOR EVERYONE"} title="Your menu" subtitle={stallName ? `Manage the items sold at ${stallName}. Other staff can’t see or change this menu.` : "Keep today’s menu fresh, clear, and ready for the lunch rush."} action={<span className="menu-date"><Icon name="clock" size={15} /> Today’s menu</span>} />
    <div className="orders-summary menu-summary"><span className="summary-spark">✳</span><span><strong>{products.filter((product) => product.available).length} items on the menu</strong><small>Toggle an item off if it’s sold out for the day.</small></span></div>
    <section className="management-card menu-create-card">
      <div className="card-heading"><div><h2>Add a menu item</h2><p>New items will appear on the student menu after saving.</p></div></div>
      {categories.length ? <form className="menu-create-form" onSubmit={submitNewItem}>
        <label>Item name<input name="item_name" type="text" maxLength={120} required /></label>
        <label>Category<select name="category_id" defaultValue="" required><option value="" disabled>Select category</option>{categories.map((item) => <option key={item.category_id} value={item.category_id}>{item.category_name}</option>)}</select></label>
        <label>Description<input name="description" type="text" maxLength={2000} /></label>
        <label>Price (₱)<input name="price" type="number" min="0.01" max="99999999" step="0.01" required /></label>
        <label>Starting stock<input name="quantity" type="number" min="0" step="1" required /></label>
        <label>Image URL (optional)<input name="image_url" type="url" maxLength={2048} placeholder="https://…" /></label>
        {error && <p className="form-error" role="alert">{error}</p>}
        <button className="primary-action" type="submit" disabled={saving}>{saving ? "Adding item…" : "Add item"} <Icon name="plus" size={14} /></button>
      </form> : <p className="table-empty">No categories are available. Ask an administrator to add a category first.</p>}
    </section>
    <div className="manage-product-grid">{products.map((product) => <article className={`manage-product ${!product.available ? "unavailable" : ""}`} key={product.id}><div className="manage-product-photo" role="img" aria-label={product.name} style={{ "--food-image": `url(${foodImage(product.image, 450, 78)})` } as CSSProperties}><span>{product.category}</span></div><div className="manage-product-body"><div className="manage-name-row"><div><h2>{product.name}</h2><p>{product.detail}</p></div><strong>{money(product.price)}</strong></div><div className="availability-row"><span className={product.available ? "available-label" : "sold-out-label"}><i />{product.available ? "Available today" : "Sold out"}</span><button type="button" className={`toggle ${product.available ? "on" : ""}`} role="switch" aria-checked={product.available} aria-label={`${product.available ? "Mark" : "Make"} ${product.name} ${product.available ? "sold out" : "available"}`} onClick={() => onToggle(product.id)}><span /></button><button className="menu-edit-button" type="button" onClick={() => { setEditError(""); setEditingProductId((current) => current === product.id ? null : product.id); }}>Edit</button></div>
      {editingProductId === product.id && <form className="menu-edit-form" onSubmit={(event) => void submitEdit(event, product.id)}>
        <label>Item name<input name="item_name" type="text" maxLength={120} defaultValue={product.name} required /></label>
        <label>Category<select name="category_id" defaultValue={String(product.categoryId ?? categories.find((item) => item.category_name === product.category)?.category_id ?? "")} required>{categories.map((item) => <option key={item.category_id} value={item.category_id}>{item.category_name}</option>)}</select></label>
        <label>Description<input name="description" type="text" maxLength={2000} defaultValue={product.detail} /></label>
        <label>Price (₱)<input name="price" type="number" min="0.01" max="99999999" step="0.01" defaultValue={product.price} required /></label>
        <label>Image URL (optional)<input name="image_url" type="url" maxLength={2048} placeholder="https://…" defaultValue={product.image.startsWith("http") ? product.image : ""} /></label>
        {editError && <p className="form-error" role="alert">{editError}</p>}
        <div className="menu-edit-actions"><button className="primary-action" type="submit" disabled={savingEdit}>{savingEdit ? "Saving…" : "Save changes"}</button><button className="secondary-action" type="button" disabled={savingEdit} onClick={() => setEditingProductId(null)}>Cancel</button></div>
      </form>}
    </div></article>)}</div>
  </div>;
}

export function InventoryView({ products, onUpdate }: { products: Product[]; onUpdate: (id: number, quantity: number) => Promise<void> }) {
  const [drafts, setDrafts] = useState<Record<number, string>>({});
  const inventory = products.map((product) => {
    const stock = product.stock ?? 0;
    return { product, stock, low: stock <= (product.reorderLevel ?? 5) };
  });
  return <div className="management-content"><PageHeading kicker="STOCK THAT MAKES LUNCH HAPPEN" title="Inventory" subtitle="Update portions and keep an eye on low-stock menu items." action={<span className="menu-date"><Icon name="clock" size={15} /> Live inventory</span>} /><div className="inventory-callout"><span className="inventory-callout-icon"><Icon name="spark" size={18} /></span><span><strong>A heads-up for the kitchen</strong><small>{inventory.filter((item) => item.low).length} menu items are at or below their reorder level.</small></span></div><section className="management-card inventory-card"><div className="card-heading"><div><h2>Today’s stock</h2><p>Stock is reserved automatically when a student places an order.</p></div><span className="today-label">LIVE</span></div><div className="inventory-list">{inventory.map(({ product, stock, low }) => { const value = drafts[product.id] ?? String(stock); return <div className="inventory-row" key={product.id}><div className="inventory-food" style={{ "--food-image": `url(${foodImage(product.image, 120, 70)})` } as CSSProperties} /><div className="inventory-name"><strong>{product.name}</strong><small>{product.category}</small></div><div className="stock-track"><span className={low ? "low" : ""} style={{ width: `${Math.min(stock * 3, 92)}%` }} /></div><label className={`stock-count ${low ? "low-text" : ""}`}><input aria-label={`${product.name} portions in stock`} type="number" min="0" value={value} onChange={(event) => setDrafts((current) => ({ ...current, [product.id]: event.target.value }))} /><small> portions</small></label><span className={low ? "low-stock-pill" : "healthy-stock-pill"}>{low ? "Low stock" : "In stock"}</span><button className="table-action" type="button" disabled={Number(value) === stock || value === ""} onClick={() => void onUpdate(product.id, Number(value)).then(() => setDrafts((current) => { const next = { ...current }; delete next[product.id]; return next; }))}>Save</button></div>; })}</div></section></div>;
}

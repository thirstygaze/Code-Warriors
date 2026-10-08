"use client";

import { useState, type FormEvent } from "react";
import PasswordInput from "./PasswordInput";
import { apiRequest, money, type AccountRole, type AdminReport, type ManagedAccount, type ManagedCategory, type Order, type Product, type View } from "../lib/smartbite";
import { Icon, PageHeading } from "./SharedComponents";
import { OrderTable } from "./OrderTable";

export function AdminDashboard({ orders, products, report, onNavigate }: { orders: Order[]; products: Product[]; report: AdminReport | null; onNavigate: (view: View) => void }) {
  const openOrders = report?.open_orders ?? orders.filter((order) => !["Completed", "Cancelled"].includes(order.status)).length;
  const peakSales = Math.max(...(report?.sales_by_day.map((day) => Number(day.total_sales)) ?? [0]), 1);
  return <div className="management-content">
    <PageHeading kicker="HOLY NAME UNIVERSITY · SCHOOL ADMIN" title="Your school, at a glance." subtitle="A clear view of canteen operations across your campus." action={<span className="admin-access-label"><Icon name="users" size={15} /> ADMIN ACCESS</span>} />
    <div className="metric-grid">
      {[
        { label: "Orders today", value: String(report?.orders_today ?? orders.length), note: "Orders placed today", tint: "mint", icon: "receipt" },
        { label: "Sales today", value: money(report?.sales_today ?? 0), note: "Excludes cancelled orders", tint: "peach", icon: "grid" },
        { label: "Active students", value: String(report?.active_students ?? 0), note: "With canteen access", tint: "lavender", icon: "users" },
        { label: "Canteen team", value: String(report?.active_staff ?? 0), note: "Active staff and admins", tint: "butter", icon: "bag" },
      ].map((metric) => <div className="metric-card" key={metric.label}><span className={`metric-icon ${metric.tint}`}><Icon name={metric.icon} size={18} /></span><span className="metric-label">{metric.label}</span><strong>{metric.value}</strong><small>{metric.note}</small></div>)}
    </div>
    <div className="admin-quick-links">
      <button type="button" onClick={() => onNavigate("Orders")}><span className="quick-link-icon peach"><Icon name="receipt" size={18} /></span><span><strong>Order activity</strong><small>{openOrders} orders need attention</small></span><Icon name="arrow" size={16} /></button>
      <button type="button" onClick={() => onNavigate("Team")}><span className="quick-link-icon lavender"><Icon name="users" size={18} /></span><span><strong>Staff directory</strong><small>Review canteen team access</small></span><Icon name="arrow" size={16} /></button>
      <button type="button" onClick={() => onNavigate("Menu")}><span className="quick-link-icon mint"><Icon name="menu" size={18} /></span><span><strong>Menu availability</strong><small>{products.filter((product) => product.available).length} items available today</small></span><Icon name="arrow" size={16} /></button>
    </div>
    <div className="admin-dashboard-columns">
      <section className="management-card sales-card">
        <div className="card-heading"><div><h2>Weekly canteen sales</h2><p>A snapshot of this week’s school-day sales.</p></div><span className="today-label">THIS WEEK</span></div>
        <div className="sales-chart" role="img" aria-label="Sales totals for the past seven days">
          {(report?.sales_by_day ?? []).map((entry) => <div className="chart-column" key={entry.order_date}><span>{money(Number(entry.total_sales))}</span><div className="chart-bar-track"><i style={{ height: `${Math.max(Number(entry.total_sales) / peakSales * 100, 2)}%` }} /></div><small>{new Date(`${entry.order_date}T00:00:00`).toLocaleDateString("en", { weekday: "short" }).toUpperCase()}</small></div>)}
          {!report?.sales_by_day.length && <div className="table-empty">No sales recorded in the last seven days.</div>}
        </div>
      </section>
      <section className="management-card admin-campus-card">
        <span className="section-eyebrow">CAMPUS PROFILE</span>
        <div className="campus-profile"><span className="school-seal">HNU</span><span><strong>Holy Name University</strong><small>School canteen</small></span><i>ACTIVE</i></div>
        <div className="campus-stat"><span>Today’s orders</span><strong>{report?.orders_today ?? orders.length}</strong></div>
        <div className="campus-stat"><span>Menu items active</span><strong>{products.filter((product) => product.available).length} of {products.length}</strong></div>
        <div className="campus-stat"><span>Open orders</span><strong>{openOrders}</strong></div>
        <button className="campus-manage-button" type="button" onClick={() => onNavigate("Team")}>View school team <Icon name="arrow" size={15} /></button>
      </section>
    </div>
    <section className="management-card admin-recent-orders"><div className="card-heading"><div><h2>Latest canteen orders</h2><p>Recent activity from your school.</p></div><button type="button" onClick={() => onNavigate("Orders")}>View orders <Icon name="arrow" size={14} /></button></div><OrderTable orders={orders.slice(0, 3)} /></section>
  </div>;
}

export function TeamView({ accounts, token, currentUserId, onRefresh, onError }: { accounts: ManagedAccount[]; token: string | null; currentUserId?: number; onRefresh: () => Promise<void>; onError: (message: string) => void }) {
  const [role, setRole] = useState<AccountRole>("staff");
  const [saving, setSaving] = useState(false);
  const [stallDrafts, setStallDrafts] = useState<Record<number, string>>({});
  const [savingStallId, setSavingStallId] = useState<number | null>(null);

  async function createAccount(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!token) return;
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    const payload: Record<string, string> = {
      name: String(form.get("name")),
      email: String(form.get("email")),
      password: String(form.get("password")),
      role,
    };
    if (role === "student") payload.student_number = String(form.get("student_number"));
    if (role === "staff") {
      payload.employee_number = String(form.get("employee_number"));
      payload.position = String(form.get("position"));
      payload.stall_name = String(form.get("stall_name")).trim();
    }
    setSaving(true);
    try {
      await apiRequest("/admin/users", token, { method: "POST", body: JSON.stringify(payload) });
      formElement.reset();
      await onRefresh();
    } catch (error) {
      onError(error instanceof Error ? error.message : "Could not create this account.");
    } finally {
      setSaving(false);
    }
  }

  async function toggleAccount(account: ManagedAccount) {
    if (!token) return;
    if (account.is_active && !window.confirm(`Deactivate ${account.name}? They will no longer be able to sign in, and their order history will be kept. You can reactivate the account later.`)) return;
    try {
      await apiRequest(`/admin/users/${account.id}`, token, {
        method: "PATCH",
        body: JSON.stringify({ is_active: !account.is_active }),
      });
      await onRefresh();
    } catch (error) {
      onError(error instanceof Error ? error.message : "Could not update this account.");
    }
  }

  async function deleteAccount(account: ManagedAccount) {
    if (!token || account.id === currentUserId) return;
    if (!window.confirm(`Permanently delete ${account.name} (${account.email})? This cannot be undone. Their account and profile will be removed, while order and payment history will remain without their account identity.`)) return;
    try {
      await apiRequest(`/admin/users/${account.id}`, token, { method: "DELETE" });
      await onRefresh();
    } catch (error) {
      onError(error instanceof Error ? error.message : "Could not permanently delete this account.");
    }
  }

  async function updateStallName(account: ManagedAccount) {
    if (!token) return;
    const stallName = (stallDrafts[account.id] ?? account.staff_profile?.stall_name ?? "").trim();
    if (!stallName) {
      onError("Enter a stall name before saving.");
      return;
    }
    setSavingStallId(account.id);
    try {
      await apiRequest(`/admin/users/${account.id}`, token, {
        method: "PATCH",
        body: JSON.stringify({ stall_name: stallName }),
      });
      setStallDrafts((current) => {
        const next = { ...current };
        delete next[account.id];
        return next;
      });
      await onRefresh();
    } catch (error) {
      onError(error instanceof Error ? error.message : "Could not update the stall name.");
    } finally {
      setSavingStallId(null);
    }
  }

  const team = accounts;
  return <div className="management-content">
    <PageHeading kicker="SCHOOL ACCESS" title="School accounts." subtitle="Create and manage student, staff, and administrator access." action={<span className="admin-access-label"><Icon name="users" size={15} /> ADMIN ACCESS</span>} />
    <div className="orders-summary"><span className="summary-spark"><Icon name="users" size={16} /></span><span><strong>{team.filter((member) => member.is_active).length} active school accounts</strong><small>Manage sign-in access for students, staff, and administrators.</small></span></div>
    <section className="management-card account-form-card">
      <div className="card-heading"><div><h2>Add a school account</h2><p>New accounts are created with the selected role and required profile.</p></div></div>
      <form className="account-create-form" onSubmit={createAccount}>
        <label>Name<input name="name" autoComplete="name" required maxLength={255} /></label>
        <label>School email<input name="email" type="email" autoComplete="email" required /></label>
        <label>Temporary password<PasswordInput name="password" autoComplete="new-password" minLength={12} required /></label>
        <label>Account role<select value={role} onChange={(event) => setRole(event.target.value as AccountRole)}><option value="student">Student</option><option value="staff">Staff</option><option value="admin">Administrator</option></select></label>
        {role === "student" && <label>Student number<input name="student_number" required /></label>}
        {role === "staff" && <><label>Stall name<input name="stall_name" required maxLength={120} /></label><label>Employee number<input name="employee_number" /></label><label>Position<input name="position" required /></label></>}
        <button className="primary-action" type="submit" disabled={saving}>{saving ? "Creating…" : "Create account"} <Icon name="arrow" size={14} /></button>
      </form>
    </section>
    <section className="management-card team-card">
      <div className="card-heading"><div><h2>School accounts</h2><p>Account access at Holy Name University.</p></div><span className="today-label">{team.length} ACCOUNTS</span></div>
      <div className="team-table-wrap"><table className="team-table"><thead><tr><th>ACCOUNT</th><th>ROLE</th><th>STALL</th><th>ACCOUNT STATUS</th><th>ACCESS</th></tr></thead><tbody>{team.map((member) => <tr key={member.id}><td><span className={`team-avatar ${member.role === "admin" ? "butter" : "mint"}`}>{member.name.split(" ").map((part) => part[0]).join("").slice(0, 2).toUpperCase()}</span><span className="team-person"><strong>{member.name}</strong><small>{member.email}</small></span></td><td><span className="team-role">{member.role === "admin" ? "Administrator" : member.role === "staff" ? "Canteen staff" : "Student"}</span></td><td>{member.role === "staff" ? <div className="team-stall-editor"><input aria-label={`${member.name} stall name`} maxLength={120} value={stallDrafts[member.id] ?? member.staff_profile?.stall_name ?? ""} onChange={(event) => setStallDrafts((current) => ({ ...current, [member.id]: event.target.value }))} /><button type="button" className="table-action" disabled={savingStallId === member.id || (stallDrafts[member.id] ?? member.staff_profile?.stall_name ?? "").trim() === (member.staff_profile?.stall_name ?? "")} onClick={() => void updateStallName(member)}>{savingStallId === member.id ? "Saving…" : "Save"}</button></div> : "—"}</td><td><span className={`team-status ${member.is_active ? "" : "inactive"}`}><i />{member.is_active ? "Active" : "Inactive"}</span></td><td><div className="account-actions"><button type="button" className="table-action" disabled={member.id === currentUserId} onClick={() => void toggleAccount(member)}>{member.is_active ? "Deactivate" : "Reactivate"}</button><button type="button" className="table-action table-action-danger" disabled={member.id === currentUserId} onClick={() => void deleteAccount(member)}>Delete permanently</button></div></td></tr>)}</tbody></table></div>
    </section>
    <div className="team-notice"><Icon name="spark" size={16} /><span>Deactivate accounts to preserve them for later reactivation. Permanent deletion removes the account and profile; order and payment history is retained without the account identity.</span></div>
  </div>;
}

export function CategoriesView({ categories, token, onRefresh, onError }: { categories: ManagedCategory[]; token: string | null; onRefresh: () => Promise<void>; onError: (message: string) => void }) {
  const [saving, setSaving] = useState(false);
  async function createCategory(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!token) return;
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    setSaving(true);
    try {
      await apiRequest("/admin/categories", token, {
        method: "POST",
        body: JSON.stringify({ category_name: String(form.get("category_name")), description: String(form.get("description") ?? "") }),
      });
      formElement.reset();
      await onRefresh();
    } catch (error) {
      onError(error instanceof Error ? error.message : "Could not create this category.");
    } finally {
      setSaving(false);
    }
  }
  async function deleteCategory(category: ManagedCategory) {
    if (!token) return;
    try {
      await apiRequest(`/admin/categories/${category.category_id}`, token, { method: "DELETE" });
      await onRefresh();
    } catch (error) {
      onError(error instanceof Error ? error.message : "Could not delete this category.");
    }
  }
  return <div className="management-content">
    <PageHeading kicker="MENU ORGANIZATION" title="Menu categories" subtitle="Keep canteen items organized for students browsing the menu." action={<span className="admin-access-label"><Icon name="grid" size={15} /> ADMIN ACCESS</span>} />
    <section className="management-card account-form-card"><div className="card-heading"><div><h2>Add a category</h2><p>Categories with menu items cannot be removed until those items are reassigned.</p></div></div><form className="account-create-form category-create-form" onSubmit={createCategory}><label>Category name<input name="category_name" required maxLength={80} /></label><label>Description<input name="description" maxLength={1000} /></label><button className="primary-action" type="submit" disabled={saving}>{saving ? "Adding…" : "Add category"} <Icon name="plus" size={14} /></button></form></section>
    <section className="management-card team-card"><div className="card-heading"><div><h2>Current categories</h2><p>Categories are shared across the school menu.</p></div><span className="today-label">{categories.length} TOTAL</span></div><div className="category-admin-list">{categories.map((category) => <article className="category-admin-row" key={category.category_id}><span className="category-admin-icon"><Icon name="menu" size={17} /></span><span className="category-admin-copy"><strong>{category.category_name}</strong><small>{category.description || "No description"}</small></span><span className="category-count">{category.menu_items_count ?? 0} items</span><button type="button" className="table-action" onClick={() => void deleteCategory(category)}>Delete</button></article>)}</div></section>
  </div>;
}

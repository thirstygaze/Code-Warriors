"use client";

import { money, type Order, type StudentNotification } from "../lib/smartbite";
import { Icon, PageHeading } from "./SharedComponents";

export function StudentOrdersView({ orders }: { orders: Order[] }) {
  const activeOrders = orders.filter((order) => !["Completed", "Cancelled"].includes(order.status));
  return <div className="management-content">
    <PageHeading kicker="YOUR LUNCH BREAK" title="My orders" subtitle="Keep an eye on the good stuff you’ve ordered." />
    <div className="orders-summary"><span className="summary-spark"><Icon name="receipt" size={16} /></span><span><strong>{activeOrders.length ? `${activeOrders.length} order${activeOrders.length === 1 ? "" : "s"} in progress` : "All caught up"}</strong><small>{activeOrders.length ? "We’ll let you know when your lunch is ready." : "Your recent canteen orders will show up here."}</small></span><span className="summary-total">{orders.length}<small> your orders</small></span></div>
    <section className="management-card orders-card"><div className="card-heading"><div><h2>Order history</h2><p>Only your own orders are shown here.</p></div><span className="today-label">MY ORDERS</span></div>
      {orders.length ? <div className="table-scroll"><table className="orders-table"><thead><tr><th>ORDER</th><th>ITEMS</th><th>TIME</th><th>TOTAL</th><th>PAYMENT</th><th>STATUS</th></tr></thead><tbody>{orders.map((order) => <tr key={order.id}><td className="order-id">{order.id}</td><td className="order-items">{order.items}</td><td>{order.time}</td><td className="table-total">{money(order.total)}</td><td><span className={`status-pill ${order.paymentStatus ?? "pending"}`}>{order.paymentStatus === "paid" ? `Paid${order.paymentMethod === "cash" ? " · Cash" : ""}` : order.paymentStatus === "failed" ? "Failed" : order.paymentMethod === "cash" ? "Cash due at pickup" : "Awaiting payment"}</span>{order.paymentMethod !== "cash" && order.paymentStatus !== "paid" && order.checkoutUrl && <a className="table-action pay-again-link" href={order.checkoutUrl}>Continue payment <Icon name="arrow" size={13} /></a>}</td><td><span className={`status-pill ${order.status.toLowerCase().replaceAll(" ", "-")}`}>{order.status}</span></td></tr>)}</tbody></table></div> : <div className="student-empty"><span className="empty-bag"><Icon name="bag" size={21} /></span><strong>Your first lunch is waiting.</strong><p>Browse the menu and place an order. We’ll keep your order history here.</p></div>}
    </section>
  </div>;
}

export function NotificationsView({ notifications, onRead }: { notifications: StudentNotification[]; onRead: (id: string) => void }) {
  return <div className="management-content">
    <PageHeading kicker="A LITTLE UPDATE FOR YOU" title="Notifications" subtitle="Pickup updates and canteen news, all in one place." />
    <section className="management-card student-notifications-card"><div className="card-heading"><div><h2>Your updates</h2><p>Private to your student account.</p></div><span className="today-label">{notifications.filter((notification) => !notification.read).length} UNREAD</span></div>
      {notifications.length ? <div className="student-notification-list">{notifications.map((notification) => <article className={`student-notification ${notification.read ? "" : "unread"}`} key={notification.id}><span className="notification-status-icon"><Icon name={notification.read ? "bell" : "spark"} size={16} /></span><div className="student-notification-copy"><strong>{notification.message}</strong><small>{notification.orderId} <i /> {notification.time}</small></div>{notification.read ? <span className="read-label">READ</span> : <button type="button" onClick={() => onRead(notification.id)}>Mark read</button>}</article>)}</div> : <div className="student-empty"><span className="empty-bag"><Icon name="bell" size={21} /></span><strong>You’re all caught up.</strong><p>Updates about your orders will appear here.</p></div>}
    </section>
  </div>;
}

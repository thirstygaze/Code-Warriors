"use client";

import { useEffect, useMemo, useState, type CSSProperties } from "react";
import { ApiRequestError, apiRequest, foodImage, initialProducts, mapApiNotification, mapApiOrder, mapApiProduct, money, pickupWindows, TOKEN_KEY, type Account, type AccountRole, type AdminReport, type Category, type ManagedAccount, type ManagedCategory, type Order, type StudentNotification, type View } from "./lib/smartbite";
import { AdminDashboard, CategoriesView, TeamView } from "./components/AdminViews";
import { LandingScreen } from "./components/LandingScreen";
import { LoginScreen } from "./components/LoginScreen";
import { ChangePasswordDialog } from "./components/ChangePasswordDialog";
import { InventoryView, MenuView, OrdersView, Overview } from "./components/StaffViews";
import { NotificationsView, StudentOrdersView } from "./components/StudentViews";
import { Icon } from "./components/SharedComponents";

export default function Home() {
  const [account, setAccount] = useState<Account | null>(null);
  const [apiToken, setApiToken] = useState<string | null>(null);
  const [authChecking, setAuthChecking] = useState(true);
  const [showLogin, setShowLogin] = useState(false);
  const [showChangePassword, setShowChangePassword] = useState(false);
  const [view, setView] = useState<View>("Order food");
  const [category, setCategory] = useState<Category>("All");
  const [query, setQuery] = useState("");
  const [products, setProducts] = useState(initialProducts);
  const [menuLive, setMenuLive] = useState(false);
  const [cart, setCart] = useState<Record<number, number>>({});
  const [paymentMethod, setPaymentMethod] = useState<"paymongo" | "cash">("paymongo");
  const [pickupTime, setPickupTime] = useState("11:30");
  const [editingPickup, setEditingPickup] = useState(false);
  const [orders, setOrders] = useState<Order[]>([]);
  const [notifications, setNotifications] = useState<StudentNotification[]>([]);
  const [managedAccounts, setManagedAccounts] = useState<ManagedAccount[]>([]);
  const [adminReport, setAdminReport] = useState<AdminReport | null>(null);
  const [managedCategories, setManagedCategories] = useState<ManagedCategory[]>([]);
  const [favorites, setFavorites] = useState<number[]>([]);
  const [notice, setNotice] = useState("");
  const [orderPlaced, setOrderPlaced] = useState(false);
  const [placedOrderCode, setPlacedOrderCode] = useState("");
  const [mobileCart, setMobileCart] = useState(false);

  useEffect(() => {
    let active = true;
    apiRequest<{ data: Parameters<typeof mapApiProduct>[0][] }>("/menu")
      .then(({ data }) => {
        if (!active) return;
        setProducts(data.map(mapApiProduct));
        setMenuLive(true);
      })
      .catch((error: unknown) => { if (active) setNotice(error instanceof Error ? error.message : "Could not load the live canteen menu."); });

    const savedToken = window.localStorage.getItem(TOKEN_KEY);
    if (!savedToken) {
      Promise.resolve().then(() => { if (active) setAuthChecking(false); });
    } else {
      apiRequest<{ user: Account }>("/auth/me", savedToken)
        .then(({ user }) => {
          if (!active) return;
          setApiToken(savedToken);
          setAccount(user);
          setView(user.role === "admin" ? "Admin dashboard" : user.role === "staff" ? "Staff dashboard" : "Order food");
          loadWorkspace(savedToken, user).catch((error: unknown) => {
            if (active) setNotice(error instanceof Error ? `Could not load your workspace: ${error.message}` : "Could not load your workspace.");
          });
        })
        .catch((error: unknown) => {
          if (error instanceof ApiRequestError && error.status === 401) {
            window.localStorage.removeItem(TOKEN_KEY);
            if (active) setNotice("Your session has expired. Please sign in again.");
            return;
          }
          if (active) setNotice(error instanceof Error
            ? `Could not verify your saved session. Check your connection and refresh again: ${error.message}`
            : "Could not verify your saved session. Check your connection and refresh again.");
        })
        .finally(() => { if (active) setAuthChecking(false); });
    }
    return () => { active = false; };
  }, []);

  async function loadWorkspace(token: string, user: Account) {
    if (user.role === "student") {
      const [orderResponse, notificationResponse] = await Promise.all([
        apiRequest<{ data: Parameters<typeof mapApiOrder>[0][] }>("/student/orders", token),
        apiRequest<{ data: { data: Parameters<typeof mapApiNotification>[0][] } }>("/student/notifications", token),
      ]);
      setOrders(orderResponse.data.map((order) => mapApiOrder(order, user.email)));
      setNotifications(notificationResponse.data.data.map((notification) => mapApiNotification(notification, user.email)));
      return;
    }
    const [orderResponse, menuResponse, categoryResponse] = await Promise.all([
      apiRequest<{ data: { data: Parameters<typeof mapApiOrder>[0][] } }>("/staff/orders", token),
      apiRequest<{ data: (Parameters<typeof mapApiProduct>[0] & { inventory?: { quantity: number; reorder_level: number } })[] }>("/staff/menu-items", token),
      apiRequest<{ data: ManagedCategory[] }>("/staff/categories", token),
    ]);
    setOrders(orderResponse.data.data.map((order) => mapApiOrder(order)));
    setProducts(menuResponse.data.map(mapApiProduct));
    setManagedCategories(categoryResponse.data);
    if (user.role === "admin") {
      const [usersResponse, reportResponse, categoryResponse] = await Promise.all([
        apiRequest<{ data: { data: ManagedAccount[] } }>("/admin/users", token),
        apiRequest<AdminReport>("/admin/reports", token),
        apiRequest<{ data: ManagedCategory[] }>("/admin/categories", token),
      ]);
      setManagedAccounts(usersResponse.data.data);
      setAdminReport(reportResponse);
      setManagedCategories(categoryResponse.data);
    }
  }

  useEffect(() => {
    if (!apiToken || !account || account.role === "student" || view !== "Orders") return;

    let active = true;
    let refreshing = false;
    const refreshOrders = async () => {
      if (refreshing) return;
      refreshing = true;
      try {
        const response = await apiRequest<{ data: { data: Parameters<typeof mapApiOrder>[0][] } }>("/staff/orders", apiToken);
        if (active) setOrders(response.data.data.map((order) => mapApiOrder(order)));
      } catch (error) {
        if (active) setNotice(error instanceof Error ? `Could not refresh orders: ${error.message}` : "Could not refresh orders.");
      } finally {
        refreshing = false;
      }
    };

    void refreshOrders();
    const interval = window.setInterval(() => { void refreshOrders(); }, 10000);
    return () => {
      active = false;
      window.clearInterval(interval);
    };
  }, [account, apiToken, view]);

  const visibleProducts = useMemo(
    () => products.filter((product) =>
      product.available &&
      (category === "All" || product.category === category) &&
      `${product.name} ${product.detail}`.toLowerCase().includes(query.toLowerCase()),
    ),
    [category, products, query],
  );
  const categoryOptions: Category[] = ["All", ...new Set(products.map((product) => product.category))];
  const cartProducts = products.filter((product) => cart[product.id]);
  const itemCount = Object.values(cart).reduce((sum, amount) => sum + amount, 0);
  const subtotal = cartProducts.reduce((sum, product) => sum + product.price * cart[product.id], 0);

  async function signIn(role: AccountRole, email: string, password: string) {
    const response = await apiRequest<{ token: string; user: Account }>("/auth/login", undefined, {
      method: "POST",
      body: JSON.stringify({ role, email, password }),
    });
    window.localStorage.setItem(TOKEN_KEY, response.token);
    setApiToken(response.token);
    setAccount(response.user);
    setShowLogin(false);
    setView(response.user.role === "admin" ? "Admin dashboard" : response.user.role === "staff" ? "Staff dashboard" : "Order food");
    try {
      await loadWorkspace(response.token, response.user);
    } catch (error) {
      setNotice(error instanceof Error ? `Signed in, but could not load your workspace: ${error.message}` : "Signed in, but could not load your workspace.");
    }
    if (response.user.role === "student" && itemCount > 0) setMobileCart(true);
  }

  async function registerStudent(name: string, email: string, studentNumber: string, password: string) {
    const response = await apiRequest<{ token: string; user: Account }>("/auth/register", undefined, {
      method: "POST",
      body: JSON.stringify({
        name,
        email,
        student_number: studentNumber,
        password,
        password_confirmation: password,
      }),
    });
    window.localStorage.setItem(TOKEN_KEY, response.token);
    setApiToken(response.token);
    setAccount(response.user);
    setShowLogin(false);
    setView("Order food");
    try {
      await loadWorkspace(response.token, response.user);
    } catch (error) {
      setNotice(error instanceof Error ? `Account created, but could not load your workspace: ${error.message}` : "Account created, but could not load your workspace.");
    }
    if (itemCount > 0) setMobileCart(true);
  }

  async function signOut() {
    let logoutIssue = "";
    if (apiToken) {
      try {
        await apiRequest("/auth/logout", apiToken, { method: "POST" });
      } catch (error) {
        logoutIssue = error instanceof Error ? `Signed out locally; server logout failed: ${error.message}` : "Signed out locally; server logout failed.";
      }
    }
    window.localStorage.removeItem(TOKEN_KEY);
    setApiToken(null);
    setAccount(null);
    setView("Order food");
    setShowLogin(false);
    setCart({});
    setMobileCart(false);
    setNotice(logoutIssue);
  }

  function changeQuantity(id: number, change: number) {
    setCart((current) => {
      const next = { ...current, [id]: Math.max(0, (current[id] ?? 0) + change) };
      if (next[id] === 0) delete next[id];
      return next;
    });
    setOrderPlaced(false);
  }

  async function placeOrder() {
    if (!itemCount) return;
    if (!menuLive) {
      setNotice("The live canteen menu is unavailable right now. Please try again in a moment.");
      return;
    }
    if (!account || !apiToken || account.role !== "student") {
      setShowLogin(true);
      return;
    }
    try {
      const response = await apiRequest<{ data: Parameters<typeof mapApiOrder>[0]; checkout_url?: string }>("/student/orders", apiToken, {
        method: "POST",
        body: JSON.stringify({
          pickup_time: pickupTime,
          payment_method: paymentMethod,
          items: cartProducts.map((product) => ({ item_id: product.id, quantity: cart[product.id] })),
        }),
      });
      const newOrder = mapApiOrder(response.data, account.email);
      setOrders((current) => [newOrder, ...current]);
      setCart({});
      setOrderPlaced(true);
      setPlacedOrderCode(newOrder.id);
      if (response.checkout_url) {
        window.location.assign(response.checkout_url);
      } else if (paymentMethod === "cash") {
        setNotice("Order saved. Pay cash at pickup; it will be sent to the kitchen after staff confirms payment.");
      } else {
        setNotice("Order was created, but PayMongo did not return a checkout link. Contact the canteen team before placing another order.");
      }
    } catch (error) {
      setNotice(error instanceof Error ? error.message : "We couldn’t place your order. Please try again.");
    }
  }

  async function toggleAvailability(id: number) {
    const product = products.find((item) => item.id === id);
    if (!product || !apiToken) return;
    const nextListed = !(product.listed ?? product.available);
    try {
      await apiRequest(`/staff/menu-items/${id}`, apiToken, {
        method: "PATCH",
        body: JSON.stringify({ availability: nextListed }),
      });
      setProducts((current) => current.map((item) => item.id === id ? { ...item, listed: nextListed, available: nextListed && (item.stock ?? 1) > 0 } : item));
    } catch (error) {
      setNotice(error instanceof Error ? error.message : "Could not update menu availability.");
    }
  }

  async function updateOrderStatus(id: string) {
    const order = orders.find((item) => item.id === id);
    if (!order?.apiId || !apiToken) return;
    const nextStatus = order.status === "Pending" ? "preparing" : order.status === "Preparing" ? "ready" : "completed";
    try {
      const response = await apiRequest<{ data: Parameters<typeof mapApiOrder>[0] }>(`/staff/orders/${order.apiId}/status`, apiToken, {
        method: "PATCH",
        body: JSON.stringify({ status: nextStatus }),
      });
      const updatedOrder = mapApiOrder(response.data);
      setOrders((current) => current.map((item) => item.id === id ? { ...item, status: updatedOrder.status } : item));
      if (account) await loadWorkspace(apiToken, account);
    } catch (error) {
      setNotice(error instanceof Error ? error.message : "Could not update order status.");
    }
  }

  async function rejectOrder(id: string) {
    const order = orders.find((item) => item.id === id);
    if (!order?.apiId || !apiToken || order.status !== "Pending" || order.paymentStatus === "paid") return;
    if (!window.confirm(`Reject ${order.id}? The order will be cancelled and its reserved items returned to inventory.`)) return;
    try {
      await apiRequest(`/staff/orders/${order.apiId}/status`, apiToken, {
        method: "PATCH",
        body: JSON.stringify({ status: "cancelled" }),
      });
      setOrders((current) => current.filter((item) => item.id !== id));
      setNotice(`${order.id} was rejected and its reserved items returned to inventory.`);
      if (account) {
        try {
          await loadWorkspace(apiToken, account);
        } catch (error) {
          setNotice(`${order.id} was rejected, but the order list could not be refreshed: ${error instanceof Error ? error.message : "Please refresh the page."}`);
        }
      }
    } catch (error) {
      setNotice(error instanceof Error ? error.message : "Could not reject order.");
    }
  }

  async function confirmCashPayment(id: string) {
    const order = orders.find((item) => item.id === id);
    if (!order?.apiId || !apiToken || order.paymentMethod !== "cash" || order.paymentStatus !== "pending") return;
    try {
      await apiRequest(`/staff/orders/${order.apiId}/cash-payment`, apiToken, { method: "POST" });
      if (account) await loadWorkspace(apiToken, account);
      setNotice(`Cash payment received for ${order.id}.`);
    } catch (error) {
      setNotice(error instanceof Error ? error.message : "Could not confirm cash payment.");
    }
  }

  const navigation: { label: View; icon: string; badge?: string }[] = !account
    ? [{ label: "Order food", icon: "bag" }]
    : account.role === "admin"
      ? [
      { label: "Admin dashboard", icon: "grid" },
      { label: "Orders", icon: "receipt", badge: String(orders.filter((order) => order.status === "Preparing").length) },
      { label: "Menu", icon: "menu" },
      { label: "Inventory", icon: "box" },
      { label: "Team", icon: "users" },
      { label: "Categories", icon: "grid" },
    ]
      : account.role === "staff"
      ? [
        { label: "Staff dashboard", icon: "grid" },
        { label: "Orders", icon: "receipt", badge: String(orders.filter((order) => order.status === "Preparing").length) },
        { label: "Menu", icon: "menu" },
        { label: "Inventory", icon: "box" },
      ]
      : [
        { label: "Order food", icon: "bag" },
        { label: "My orders", icon: "receipt", badge: String(orders.filter((order) => order.studentEmail === account?.email && !["Completed", "Cancelled"].includes(order.status)).length) },
        { label: "Notifications", icon: "bell", badge: String(notifications.filter((notification) => notification.studentEmail === account?.email && !notification.read).length) },
      ];

  if (authChecking) return <main className="auth-loading" role="status"><span className="brand-mark">s<span>.</span></span><p>Checking your Smartbite session…</p></main>;
  if (showLogin && !account) return <LoginScreen onSignIn={signIn} onRegister={registerStudent} onBack={() => setShowLogin(false)} />;
  if (!account) return <LandingScreen onSignIn={() => { setNotice(""); setShowLogin(true); }} notice={notice} />;

  const accountInitials = account?.name.split(" ").map((part) => part[0]).join("").slice(0, 2).toUpperCase();

  return (
    <main className={`app-shell ${account ? "" : "public-home"}`}>
      <aside className="sidebar">
        <a className="brand" href="#" onClick={(event) => { event.preventDefault(); setView(account?.role === "admin" ? "Admin dashboard" : account?.role === "staff" ? "Staff dashboard" : "Order food"); }}>
          <span className="brand-mark">s<span>.</span></span>
          <span className="brand-name">Smartbite</span>
        </a>
        <div className="school-label">YOUR SCHOOL</div>
        <button className="school-switcher" type="button">
          <span className="school-seal">HNU</span>
          <span className="school-copy"><strong>Holy Name University</strong><small>Campus canteen</small></span>
          <span className="switch-chevron">⌄</span>
        </button>
        <div className="side-section-label">{!account ? "CANTEEN" : account.role === "student" ? "STUDENT" : account.role === "admin" ? "ADMINISTRATION" : "WORKSPACE"}</div>
        <nav className="side-nav" aria-label="Main navigation">
          {navigation.map((item) => (
            <button
              key={item.label}
              className={`nav-item ${view === item.label ? "active" : ""}`}
              type="button"
              onClick={() => {
                setView(item.label);
                setMobileCart(false);
                if (apiToken && account) {
                  loadWorkspace(apiToken, account).catch((error: unknown) => setNotice(error instanceof Error ? error.message : "Could not refresh your workspace."));
                }
              }}
            >
              <Icon name={item.icon} size={19} />
              <span>{item.label}</span>
              {item.badge && <span className="nav-badge">{item.badge}</span>}
            </button>
          ))}
        </nav>
        <div className="sidebar-spacer" />
        <div className="sidebar-help">
          <div className="help-orb"><Icon name="spark" size={18} /></div>
          <strong>Need a hand?</strong>
          <p>We’re here to make lunch easier.</p>
          <button type="button" onClick={() => setNotice("Ask the canteen team at counter 1 — we’re happy to help!")}>Get in touch <Icon name="arrow" size={14} /></button>
        </div>
        {account ? (
          <button className="profile" type="button" onClick={() => setShowChangePassword(true)} aria-label="Change password">
            <span className="avatar">{accountInitials}</span>
            <span className="profile-copy"><strong>{account.name}</strong><small>{account.role === "admin" ? "School administrator" : account.role === "staff" ? "Canteen staff" : "Student account"}</small></span>
            <span className="profile-dots"><Icon name="key" size={16} /></span>
          </button>
        ) : (
          <button className="sidebar-signin" type="button" onClick={() => setShowLogin(true)}><Icon name="users" size={17} /> Sign in to your account <Icon name="arrow" size={15} /></button>
        )}
      </aside>

      <section className="workspace">
        <header className="topbar">
          <div className="breadcrumb"><span>Holy Name University</span><span className="crumb-slash">/</span><strong>{view === "Order food" ? "Canteen" : view}</strong></div>
          <div className="topbar-actions">
            <span className="open-status"><i /> Canteen open <span className="status-until">· start 6:00 AM until 6:00 PM</span></span>
            <button className="icon-button notification-button" type="button" aria-label="Notifications" onClick={() => {
              if (account?.role === "student") {
                setView("Notifications");
                if (apiToken) loadWorkspace(apiToken, account).catch((error: unknown) => setNotice(error instanceof Error ? error.message : "Could not load notifications."));
              } else {
                setNotice(account ? "Student order notifications are in the Notifications workspace." : "Sign in to view your notifications.");
              }
            }}><Icon name="bell" size={19} /><i /></button>
            {account && <button className="icon-button" type="button" aria-label="Change password" title="Change password" onClick={() => setShowChangePassword(true)}><Icon name="key" size={17} /></button>}
            {account
              ? <button className="top-avatar" type="button" aria-label={`Log out ${account.name}`} title="Log out" onClick={signOut}><Icon name="logout" size={16} /></button>
              : <button className="top-signin" type="button" onClick={() => setShowLogin(true)}>Sign in <Icon name="arrow" size={15} /></button>}
          </div>
        </header>

        {view === "Order food" && (
          <div className="page-content order-layout">
            <div className="menu-column">
              <div className="greeting-row">
                <div><div className="eyebrow"><span className="eyebrow-spark">✳</span> YOUR SCHOOL DAY</div><h1>{account ? `Good morning, ${account.name.split(" ")[0]}` : "Lunch, made easy"} <span className="wave">✳</span></h1><p className="page-subtitle">A little something good is just a few clicks away.</p></div>
                {account && <div className="pickup-chip"><span className="pickup-icon"><Icon name="clock" size={17} /></span><span><small>YOUR PICKUP WINDOW</small><strong>{pickupWindows.find((window) => window.value === pickupTime)?.label}</strong></span></div>}
              </div>
              <div className="hero-banner">
                <div className="hero-copy"><div className="hero-kicker"><span /> TODAY’S LUNCH SPECIAL</div><h2>Good food.<br />Good mood.</h2><p>Freshly made, always worth the break.</p><button type="button" onClick={() => { setCategory("All"); document.getElementById("menu-section")?.scrollIntoView({ behavior: "smooth" }); }}>Find your lunch <Icon name="arrow" size={16} /></button></div>
                <div className="hero-food" role="img" aria-label="Delicious chicken rice bowl" />
                <div className="hero-sticker"><span>made</span><strong>with ♥</strong><span>at school</span></div>
                <span className="hero-decoration decor-one">✳</span><span className="hero-decoration decor-two">·</span>
              </div>
              <div className="menu-header" id="menu-section">
                <div><div className="section-eyebrow">THE GOOD STUFF</div><h2>What sounds good?</h2></div>
                <label className="search-field"><Icon name="search" size={17} /><input aria-label="Search menu" placeholder="Find something…" value={query} onChange={(event) => setQuery(event.target.value)} />{query && <button type="button" aria-label="Clear search" onClick={() => setQuery("")}><Icon name="close" size={15} /></button>}</label>
              </div>
              <div className="category-row" role="group" aria-label="Filter by category">
                {categoryOptions.map((item) => <button type="button" key={item} className={`category-chip ${category === item ? "selected" : ""}`} onClick={() => setCategory(item)}>{item}{item === "All" && <span>{products.filter((product) => product.available).length}</span>}</button>)}
              </div>
              <div className="product-grid">
                {visibleProducts.map((product) => (
                  <article className="product-card" key={product.id}>
                    <div className="product-photo" role="img" aria-label={product.name} style={{ "--food-image": `url(${foodImage(product.image, 680, 82)})` } as CSSProperties}>
                      {product.tag && <span className="food-tag">{product.tag}</span>}
                      <button className={`favorite-button ${favorites.includes(product.id) ? "favorited" : ""}`} type="button" aria-label={favorites.includes(product.id) ? "Remove from favorites" : "Add to favorites"} onClick={() => setFavorites((current) => current.includes(product.id) ? current.filter((id) => id !== product.id) : [...current, product.id])}><Icon name="heart" size={17} /></button>
                    </div>
                    <div className="product-info"><div><span className="product-category">{product.category}{product.stallName ? ` · ${product.stallName}` : ""}</span><h3>{product.name}</h3></div><strong className="product-price">{money(product.price)}</strong></div>
                    <p className="product-detail">{product.detail}</p>
                    <button className={`add-button ${cart[product.id] ? "added" : ""}`} type="button" disabled={!menuLive} onClick={() => changeQuantity(product.id, 1)}>{!menuLive ? "Connecting…" : cart[product.id] ? <><Icon name="check" size={16} /> Add another · {cart[product.id]} in bag</> : <><Icon name="plus" size={16} /> Add to order</>}</button>
                  </article>
                ))}
                {!visibleProducts.length && <div className="empty-menu"><span>◌</span><strong>Nothing on the menu just yet</strong><p>Try another category or search term.</p><button type="button" onClick={() => { setQuery(""); setCategory("All"); }}>Show everything</button></div>}
              </div>
              <p className="menu-footnote"><Icon name="spark" size={15} /> Made fresh every school day, with a little extra love.</p>
            </div>

            <aside className={`cart-panel ${mobileCart ? "mobile-open" : ""}`} aria-label="Your order">
              <div className="cart-heading"><div><span className="section-eyebrow">LUNCH PLANS</span><h2>Your order <span className="cart-count">{itemCount}</span></h2></div><button className="cart-close icon-button" type="button" aria-label="Close order" onClick={() => setMobileCart(false)}><Icon name="close" size={18} /></button></div>
              {orderPlaced && <div className="order-success"><span className="success-check"><Icon name="check" size={16} /></span><span><strong>Order saved · Code {placedOrderCode}</strong><small>{paymentMethod === "cash" ? "Show this code to staff when you pick up and pay." : "Use this code to identify your order after completing PayMongo checkout."}</small></span></div>}
              <div className="pickup-card"><span className="pickup-card-icon"><Icon name="clock" size={17} /></span><span className="pickup-card-time"><small>ESTIMATED PICKUP</small><strong>Today, {pickupWindows.find((window) => window.value === pickupTime)?.label}</strong>{editingPickup && <select className="pickup-select" aria-label="Choose pickup time" value={pickupTime} onChange={(event) => { setPickupTime(event.target.value); setEditingPickup(false); }}>{pickupWindows.map((window) => <option key={window.value} value={window.value}>{window.label}</option>)}</select>}</span><button className="pickup-edit" type="button" aria-expanded={editingPickup} onClick={() => setEditingPickup((editing) => !editing)}>{editingPickup ? "DONE" : "EDIT"}</button></div>
              {cartProducts.length ? <div className="cart-items">
                {cartProducts.map((product) => <div className="cart-item" key={product.id}>
                  <div className="cart-thumb" style={{ "--food-image": `url(${foodImage(product.image, 150, 75)})` } as CSSProperties} />
                  <div className="cart-item-info"><strong>{product.name}</strong><span>{money(product.price)} each</span><div className="quantity-control"><button type="button" aria-label={`Remove one ${product.name}`} onClick={() => changeQuantity(product.id, -1)}><Icon name="minus" size={13} /></button><span>{cart[product.id]}</span><button type="button" aria-label={`Add one ${product.name}`} onClick={() => changeQuantity(product.id, 1)}><Icon name="plus" size={13} /></button></div></div>
                  <strong className="cart-line-total">{money(product.price * cart[product.id])}</strong>
                </div>)}
              </div> : <div className="cart-empty"><div className="empty-bag"><Icon name="bag" size={23} /></div><strong>Your lunch break starts here</strong><p>Add something delicious from the menu and we’ll save it for you.</p><button type="button" onClick={() => setMobileCart(false)}>Explore the menu <Icon name="arrow" size={15} /></button></div>}
              <div className="cart-bottom">
                <fieldset className="payment-method-picker">
                  <legend>PAYMENT METHOD</legend>
                  <label><input type="radio" name="payment-method" value="paymongo" checked={paymentMethod === "paymongo"} onChange={() => setPaymentMethod("paymongo")} /><span><strong>Pay online</strong><small>Card · Maya · GCash · GrabPay · QR Ph</small></span></label>
                  <label><input type="radio" name="payment-method" value="cash" checked={paymentMethod === "cash"} onChange={() => setPaymentMethod("cash")} /><span><strong>Cash at pickup</strong><small>Pay staff when collecting</small></span></label>
                </fieldset>
                <button className="add-note" type="button" onClick={() => setNotice("Dietary note: please speak with the canteen team before placing your order.")}><span>＋</span> Add a note or dietary request</button>
                <div className="subtotal-row"><span>Subtotal <span className="tax-note">· taxes included</span></span><strong>{money(subtotal)}</strong></div>
                <button className="checkout-button" type="button" disabled={!itemCount} onClick={placeOrder}>{account ? "Place my order" : "Sign in to checkout"} <Icon name="arrow" size={17} /></button>
                <p className="checkout-note"><Icon name={account?.role === "student" ? "check" : "users"} size={13} /> {paymentMethod === "cash" ? "Cash orders are prepared after staff confirms receipt" : account?.role === "student" ? "Secure online checkout by PayMongo" : "Your cart will be saved while you sign in"}</p>
              </div>
            </aside>
          </div>
        )}

        {view === "Staff dashboard" && <Overview orders={orders} products={products} onNavigate={setView} />}
        {view === "Admin dashboard" && <AdminDashboard orders={orders} products={products} report={adminReport} onNavigate={setView} />}
        {account?.role === "student" && view === "My orders" && <StudentOrdersView orders={orders.filter((order) => order.studentEmail === account?.email)} />}
        {account?.role === "student" && view === "Notifications" && <NotificationsView notifications={notifications.filter((notification) => notification.studentEmail === account?.email)} onRead={(id) => {
          const notification = notifications.find((item) => item.id === id);
          if (!notification?.apiId || !apiToken) return;
          apiRequest(`/student/notifications/${notification.apiId}/read`, apiToken, { method: "PATCH" })
            .then(() => setNotifications((current) => current.map((item) => item.id === id ? { ...item, read: true } : item)))
            .catch((error: unknown) => setNotice(error instanceof Error ? error.message : "Could not mark notification as read."));
        }} />}
        {view === "Orders" && <OrdersView orders={orders} onStatusChange={(id) => { void updateOrderStatus(id); }} onCashReceived={(id) => { void confirmCashPayment(id); }} onCancel={(id) => { void rejectOrder(id); }} />}
        {view === "Menu" && <MenuView products={products} categories={managedCategories} stallName={account?.staff_profile?.stall_name} onToggle={toggleAvailability} onCreate={async (item) => {
          if (!apiToken) throw new Error("Sign in again to add a menu item.");
          const response = await apiRequest<{ data: Parameters<typeof mapApiProduct>[0] & { inventory?: { quantity: number; reorder_level: number } } }>("/staff/menu-items", apiToken, {
            method: "POST",
            body: JSON.stringify(item),
          });
          const product = mapApiProduct(response.data);
          setProducts((current) => [product, ...current]);
        }} onEdit={async (id, item) => {
          if (!apiToken) throw new Error("Sign in again to edit menu items.");
          const response = await apiRequest<{ data: Parameters<typeof mapApiProduct>[0] & { inventory?: { quantity: number; reorder_level: number } } }>(`/staff/menu-items/${id}`, apiToken, {
            method: "PATCH",
            body: JSON.stringify(item),
          });
          const product = mapApiProduct(response.data);
          setProducts((current) => current.map((existing) => existing.id === id ? product : existing));
        }} />}
        {view === "Inventory" && <InventoryView products={products} onUpdate={async (id, quantity) => {
          if (!apiToken) return;
          try {
            const product = products.find((item) => item.id === id);
            await apiRequest(`/staff/inventory/${id}`, apiToken, { method: "PUT", body: JSON.stringify({ quantity, reorder_level: product?.reorderLevel ?? 5 }) });
            setProducts((current) => current.map((item) => item.id === id ? { ...item, stock: quantity, available: quantity > 0 && (item.listed ?? true) } : item));
          } catch (error) {
            setNotice(error instanceof Error ? error.message : "Could not update inventory.");
          }
        }} />}
        {view === "Team" && <TeamView accounts={managedAccounts} token={apiToken} currentUserId={account?.id} onRefresh={() => apiToken && account ? loadWorkspace(apiToken, account) : Promise.resolve()} onError={(message) => setNotice(message)} />}
        {view === "Categories" && <CategoriesView categories={managedCategories} token={apiToken} onRefresh={() => apiToken && account ? loadWorkspace(apiToken, account) : Promise.resolve()} onError={(message) => setNotice(message)} />}
      </section>

      {notice && <div className="toast-message" role="status"><span><Icon name="check" size={16} /></span>{notice}<button type="button" aria-label="Dismiss notification" onClick={() => setNotice("")}><Icon name="close" size={16} /></button></div>}
      {showChangePassword && apiToken && <ChangePasswordDialog token={apiToken} onClose={() => setShowChangePassword(false)} />}
      {(!account || account.role === "student") && view === "Order food" && <button className="mobile-cart-button" type="button" onClick={() => setMobileCart(true)}><Icon name="bag" size={18} /> Your order <span>{itemCount}</span><strong>{money(subtotal)}</strong></button>}
    </main>
  );
}

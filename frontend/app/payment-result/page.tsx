"use client";

import { Suspense, useEffect, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";

const API_URL = (process.env.NEXT_PUBLIC_SMARTBITE_API_URL ?? "http://localhost:8000/api").replace(/\/$/, "");
const TOKEN_KEY = "smartbite-api-token";

type PaymentResult = {
  order_id: number;
  total_amount: string | number;
  payment?: {
    payment_status: "pending" | "paid" | "failed";
    checkout_url?: string | null;
  };
};

export default function PaymentResultPage() {
  return <Suspense fallback={<main className="payment-result-page"><p>Loading payment status…</p></main>}><PaymentResultContent /></Suspense>;
}

function PaymentResultContent() {
  const searchParams = useSearchParams();
  const orderId = searchParams.get("order_id") ?? "";
  const result = searchParams.get("result") ?? "";
  const [order, setOrder] = useState<PaymentResult | null>(null);
  const [error, setError] = useState("");
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    let active = true;
    async function checkPayment() {
      const token = window.localStorage.getItem(TOKEN_KEY);
      if (!token || !orderId) {
        if (active) {
          setError("Sign in with the student account used for this order to check payment status.");
          setChecking(false);
        }
        return;
      }

      try {
        for (let attempt = 0; attempt < 5; attempt += 1) {
          const response = await fetch(`${API_URL}/student/orders/${encodeURIComponent(orderId)}/payment`, {
            headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
          });
          const payload = await response.json() as { data?: PaymentResult; message?: string };
          if (!response.ok || !payload.data) throw new Error(payload.message ?? "Could not check this order’s payment.");
          if (!active) return;
          setOrder(payload.data);
          if (payload.data.payment?.payment_status !== "pending") break;
          if (attempt < 4 && result === "success") await new Promise((resolve) => window.setTimeout(resolve, 2000));
          else break;
        }
      } catch (paymentError) {
        if (active) setError(paymentError instanceof Error ? paymentError.message : "Could not check this order’s payment.");
      } finally {
        if (active) setChecking(false);
      }
    }

    void checkPayment();
    return () => { active = false; };
  }, [orderId, result]);

  const paid = order?.payment?.payment_status === "paid";
  const paymentFailed = order?.payment?.payment_status === "failed";

  return <main className="payment-result-page">
    <section className="payment-result-card">
      <Link className="brand" href="/"><span className="brand-mark">s<span>.</span></span><span className="brand-name">Smartbite</span></Link>
      <div className="auth-eyebrow"><span>✳</span> HOLY NAME UNIVERSITY</div>
      <h1>{paid ? "Payment confirmed." : paymentFailed ? "Payment wasn’t completed." : result === "cancelled" ? "Payment paused." : "Checking your payment."}</h1>
      {order && <div className={`payment-result-status ${paid ? "paid" : ""}`} role="status">
        <strong>{paid ? "Paid" : paymentFailed ? "Payment failed" : "Awaiting PayMongo confirmation"}</strong>
        <span>Order #SB-{order.order_id} · ₱{Number(order.total_amount).toFixed(2)}</span>
      </div>}
      {checking && <p className="auth-intro">Checking the secure payment confirmation from PayMongo…</p>}
      {!checking && !error && !paid && !paymentFailed && <p className="auth-intro">{result === "cancelled" ? "Your order is saved. You can resume payment below; it will not be sent to the canteen until PayMongo confirms payment." : "PayMongo has not confirmed this payment yet. Check your order history in a moment for the latest status."}</p>}
      {paid && <p className="auth-intro">Your order is paid and has been sent to the canteen. You can track preparation in your order history.</p>}
      {paymentFailed && <p className="auth-intro">Payment failed. Please return to the canteen menu to place a new order, or contact the canteen team if you were charged.</p>}
      {error && <p className="form-error" role="alert">{error}</p>}
      {!checking && !error && !paid && !paymentFailed && order?.payment?.checkout_url && <a className="auth-submit payment-resume-link" href={order.payment.checkout_url}>Continue payment</a>}
      <Link className="reset-back-link" href="/">Back to Smartbite</Link>
    </section>
  </main>;
}

"use client";

import { Suspense, useState, type FormEvent } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import PasswordInput from "../components/PasswordInput";

const API_URL = (process.env.NEXT_PUBLIC_SMARTBITE_API_URL ?? "http://localhost:8000/api").replace(/\/$/, "");

export default function ResetPasswordPage() {
  return <Suspense fallback={<main className="reset-password-page"><p>Loading password reset…</p></main>}><ResetPasswordForm /></Suspense>;
}

function ResetPasswordForm() {
  const searchParams = useSearchParams();
  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  async function resetPassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setMessage("");
    setSaving(true);
    try {
      const response = await fetch(`${API_URL}/auth/reset-password`, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify({
          token,
          email,
          password,
          password_confirmation: passwordConfirmation,
        }),
      });
      const payload = await response.json() as { message?: string };
      if (!response.ok) throw new Error(payload.message ?? "This reset link is invalid or expired.");
      setMessage(payload.message ?? "Your password has been reset.");
      setPassword("");
      setPasswordConfirmation("");
    } catch (resetError) {
      setError(resetError instanceof Error ? resetError.message : "Could not reset your password.");
    } finally {
      setSaving(false);
    }
  }

  return <main className="reset-password-page">
    <section className="reset-password-card">
      <Link className="brand" href="/"><span className="brand-mark">s<span>.</span></span><span className="brand-name">Smartbite</span></Link>
      <div className="auth-eyebrow"><span>✳</span> HOLY NAME UNIVERSITY</div>
      <h1>Choose a new password.</h1>
      <p className="auth-intro">Use at least 12 characters, and choose one you have not used before.</p>
      <form className="auth-form" onSubmit={resetPassword}>
        <label className="auth-label" htmlFor="reset-new-password">NEW PASSWORD</label>
        <PasswordInput className="auth-input" id="reset-new-password" autoComplete="new-password" minLength={12} value={password} onChange={(event) => setPassword(event.target.value)} required />
        <label className="auth-label" htmlFor="reset-confirm-password">CONFIRM PASSWORD</label>
        <PasswordInput className="auth-input" id="reset-confirm-password" autoComplete="new-password" minLength={12} value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} required />
        {error && <p className="form-error" role="alert">{error}</p>}
        {message && <p className="reset-success" role="status">{message}</p>}
        <button className="auth-submit" type="submit" disabled={saving}>{saving ? "Updating…" : "Reset password"}</button>
      </form>
      <Link className="reset-back-link" href="/">Back to Smartbite sign in</Link>
    </section>
  </main>;
}

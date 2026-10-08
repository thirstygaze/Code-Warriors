"use client";

import { useState, type FormEvent } from "react";
import PasswordInput from "./PasswordInput";
import { apiRequest, type AccountRole } from "../lib/smartbite";
import { Icon } from "./SharedComponents";

export function LoginScreen({ onSignIn, onRegister, onBack }: { onSignIn: (role: AccountRole, email: string, password: string) => Promise<void>; onRegister: (name: string, email: string, studentNumber: string, password: string) => Promise<void>; onBack: () => void }) {
  const [screen, setScreen] = useState<"login" | "forgot" | "register">("login");
  const [role, setRole] = useState<AccountRole>("student");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [name, setName] = useState("");
  const [studentNumber, setStudentNumber] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [resetEmail, setResetEmail] = useState("");
  const [resetSubmitted, setResetSubmitted] = useState(false);
  const [error, setError] = useState("");
  const [registerError, setRegisterError] = useState("");
  const [resetError, setResetError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function submitLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setSubmitting(true);
    try {
      await onSignIn(role, email, password);
    } catch (loginError) {
      setError(loginError instanceof Error ? loginError.message : "Unable to sign in. Please try again.");
    } finally {
      setSubmitting(false);
    }
  }

  async function submitRegistration(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setRegisterError("");
    if (password !== confirmPassword) {
      setRegisterError("The passwords do not match.");
      return;
    }
    setSubmitting(true);
    try {
      await onRegister(name, email, studentNumber, password);
    } catch (registrationError) {
      setRegisterError(registrationError instanceof Error ? registrationError.message : "Unable to create your account. Please try again.");
    } finally {
      setSubmitting(false);
    }
  }

  async function submitReset(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setResetError("");
    setSubmitting(true);
    try {
      await apiRequest("/auth/forgot-password", undefined, {
        method: "POST",
        body: JSON.stringify({ email: resetEmail }),
      });
      setResetSubmitted(true);
    } catch (resetRequestError) {
      setResetError(resetRequestError instanceof Error ? resetRequestError.message : "Could not request a password reset.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="auth-page">
      <section className="auth-visual">
        <a className="brand auth-brand" href="#" onClick={(event) => event.preventDefault()}>
          <span className="brand-mark">s<span>.</span></span>
          <span className="brand-name">Smartbite</span>
        </a>
        <div className="auth-story">
          <span className="auth-story-kicker"><i /> HOLY NAME UNIVERSITY</span>
          <h1>Good days<br />start at the<br /><em>lunch table.</em></h1>
          <p>Fresh food, smoother breaks, and a happier school day — all in one place.</p>
          <div className="auth-food-photo" role="img" aria-label="A fresh, colorful school lunch bowl" />
          <div className="auth-quote"><span>“</span><p>A little something good, right when you need it.</p><small>YOUR SCHOOL CANTEEN</small></div>
        </div>
        <span className="auth-visual-footer">MADE FOR THE SCHOOL DAY <span>✳</span></span>
      </section>
      <section className="auth-form-side">
        <div className="auth-form-wrap">
          <button className="auth-back auth-return-menu" type="button" onClick={onBack}><Icon name="arrow" size={15} /> Back to welcome</button>
          <a className="brand auth-mobile-brand" href="#" onClick={(event) => event.preventDefault()}>
            <span className="brand-mark">s<span>.</span></span>
            <span className="brand-name">Smartbite</span>
          </a>
          {screen === "login" ? (
            <>
              <div className="auth-eyebrow"><span>✳</span> WELCOME TO YOUR CANTEEN</div>
              <h2>Good to have you.</h2>
              <p className="auth-intro">Sign in to make your next school lunch a little easier.</p>
              <form className="auth-form" onSubmit={submitLogin}>
                <label className="auth-label">I’M SIGNING IN AS</label>
                <div className="role-picker" role="group" aria-label="Choose account type">
                  {([
                    { value: "student" as const, label: "Student" },
                    { value: "staff" as const, label: "Staff" },
                    { value: "admin" as const, label: "Admin" },
                  ]).map((option) => (
                    <button className={`role-option ${role === option.value ? "selected" : ""}`} type="button" key={option.value} aria-pressed={role === option.value} onClick={() => setRole(option.value)}>
                      {option.value === "student" ? <Icon name="users" size={15} /> : option.value === "staff" ? <Icon name="bag" size={15} /> : <Icon name="grid" size={15} />}
                      {option.label}
                    </button>
                  ))}
                </div>
                <label className="auth-label" htmlFor="login-email">SCHOOL EMAIL</label>
                <input className="auth-input" id="login-email" name="email" type="email" autoComplete="username" placeholder="you@hnu.edu.ph" value={email} onChange={(event) => setEmail(event.target.value)} required />
                <div className="password-label-row"><label className="auth-label" htmlFor="login-password">PASSWORD</label><button className="text-link" type="button" onClick={() => { setScreen("forgot"); setResetEmail(email); setResetSubmitted(false); }}>Forgot password?</button></div>
                <PasswordInput className="auth-input" id="login-password" name="password" autoComplete="current-password" placeholder="Enter your password" value={password} onChange={(event) => setPassword(event.target.value)} required />
                {error && <p className="form-error" role="alert">{error}</p>}
                <button className="auth-submit" type="submit" disabled={submitting}>{submitting ? "Signing in…" : "Sign in"} {!submitting && <Icon name="arrow" size={17} />}</button>
              </form>
              <div className="demo-notice"><Icon name="spark" size={15} /><span>Staff and administrators sign in with accounts provided by the canteen administrator.</span></div>
              {role === "student" && <p className="auth-intro">New student? <button className="text-link" type="button" onClick={() => { setScreen("register"); setError(""); }}>Create an account</button></p>}
              <p className="auth-school-note"><span>HNU</span> Holy Name University <i /> School canteen portal</p>
            </>
          ) : screen === "register" ? (
            <>
              <button className="auth-back" type="button" onClick={() => setScreen("login")}><Icon name="arrow" size={15} /> Back to sign in</button>
              <div className="auth-eyebrow"><span>✳</span> STUDENT REGISTRATION</div>
              <h2>Create your account.</h2>
              <p className="auth-intro">Sign up to order from your school canteen.</p>
              <form className="auth-form reset-form" onSubmit={submitRegistration}>
                <label className="auth-label" htmlFor="register-name">FULL NAME</label>
                <input className="auth-input" id="register-name" name="name" type="text" autoComplete="name" placeholder="Your full name" value={name} onChange={(event) => setName(event.target.value)} required />
                <label className="auth-label" htmlFor="register-student-number">STUDENT NUMBER</label>
                <input className="auth-input" id="register-student-number" name="student_number" type="text" autoComplete="off" placeholder="Your student number" value={studentNumber} onChange={(event) => setStudentNumber(event.target.value)} required />
                <label className="auth-label" htmlFor="register-email">SCHOOL EMAIL</label>
                <input className="auth-input" id="register-email" name="email" type="email" autoComplete="email" placeholder="you@hnu.edu.ph" value={email} onChange={(event) => setEmail(event.target.value)} required />
                <label className="auth-label" htmlFor="register-password">PASSWORD (12 CHARACTERS MINIMUM)</label>
                <PasswordInput className="auth-input" id="register-password" name="password" autoComplete="new-password" placeholder="Create a password" value={password} onChange={(event) => setPassword(event.target.value)} minLength={12} required />
                <label className="auth-label" htmlFor="register-password-confirmation">CONFIRM PASSWORD</label>
                <PasswordInput className="auth-input" id="register-password-confirmation" name="password_confirmation" autoComplete="new-password" placeholder="Enter your password again" value={confirmPassword} onChange={(event) => setConfirmPassword(event.target.value)} minLength={12} required />
                {registerError && <p className="form-error" role="alert">{registerError}</p>}
                <button className="auth-submit" type="submit" disabled={submitting}>{submitting ? "Creating account…" : "Create account"} {!submitting && <Icon name="arrow" size={17} />}</button>
              </form>
              <p className="auth-intro">Already registered? <button className="text-link" type="button" onClick={() => setScreen("login")}>Sign in</button></p>
              <p className="auth-school-note"><span>HNU</span> Holy Name University <i /> School canteen portal</p>
            </>
          ) : (
            <>
              <button className="auth-back" type="button" onClick={() => setScreen("login")}><Icon name="arrow" size={15} /> Back to sign in</button>
              <div className="auth-eyebrow"><span>✳</span> ACCOUNT RECOVERY</div>
              <h2>Forgot your password?</h2>
              {!resetSubmitted ? (
                <>
                  <p className="auth-intro">Enter your school email and we’ll help you get back into your account.</p>
                  <form className="auth-form reset-form" onSubmit={submitReset}>
                    <label className="auth-label" htmlFor="reset-email">SCHOOL EMAIL</label>
                    <input className="auth-input" id="reset-email" name="email" type="email" autoComplete="email" placeholder="you@hnu.edu.ph" value={resetEmail} onChange={(event) => setResetEmail(event.target.value)} required />
                    {resetError && <p className="form-error" role="alert">{resetError}</p>}
                    <button className="auth-submit" type="submit" disabled={submitting}>{submitting ? "Sending…" : "Send reset link"} {!submitting && <Icon name="arrow" size={17} />}</button>
                  </form>
                  <div className="demo-notice reset-notice"><Icon name="spark" size={15} /><span>For privacy, we’ll show the same confirmation whether or not the email matches an account.</span></div>
                </>
              ) : (
                <div className="reset-confirmation" role="status">
                  <span className="reset-confirm-icon"><Icon name="check" size={21} /></span>
                  <h3>Check your school email</h3>
                  <p>If an account exists for that email, we’ve sent a secure password reset link. The link expires after an hour.</p>
                  <button className="auth-submit" type="button" onClick={() => setScreen("login")}>Back to sign in <Icon name="arrow" size={17} /></button>
                </div>
              )}
              <p className="auth-school-note"><span>HNU</span> Holy Name University <i /> School canteen portal</p>
            </>
          )}
        </div>
      </section>
    </main>
  );
}

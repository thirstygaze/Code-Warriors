"use client";

import { useState, type FormEvent } from "react";
import { apiRequest } from "../lib/smartbite";
import PasswordInput from "./PasswordInput";
import { Icon } from "./SharedComponents";

export function ChangePasswordDialog({ token, onClose }: { token: string; onClose: () => void }) {
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [changed, setChanged] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setSubmitting(true);

    try {
      await apiRequest("/auth/change-password", token, {
        method: "POST",
        body: JSON.stringify({
          current_password: currentPassword,
          password: newPassword,
          password_confirmation: confirmPassword,
        }),
      });
      setChanged(true);
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Could not change your password.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="password-dialog-backdrop" onMouseDown={(event) => {
      if (event.target === event.currentTarget) onClose();
    }}>
      <section className="password-dialog" role="dialog" aria-modal="true" aria-labelledby="change-password-title">
        <button className="password-dialog-close icon-button" type="button" aria-label="Close" onClick={onClose}>
          <Icon name="close" size={17} />
        </button>
        {changed ? (
          <>
            <div className="password-dialog-icon"><Icon name="check" size={19} /></div>
            <h2 id="change-password-title">Password changed</h2>
            <p>Your new password is active. Other signed-in sessions have been signed out.</p>
            <button className="auth-submit" type="button" onClick={onClose}>Done</button>
          </>
        ) : (
          <>
            <div className="section-eyebrow">ACCOUNT SECURITY</div>
            <h2 id="change-password-title">Change your password</h2>
            <p>Enter your current password, then choose a new one with at least 12 characters.</p>
            <form className="password-dialog-form" onSubmit={submit}>
              <label className="auth-label" htmlFor="current-password">CURRENT PASSWORD</label>
              <PasswordInput
                className="auth-input"
                id="current-password"
                name="current_password"
                autoComplete="current-password"
                value={currentPassword}
                onChange={(event) => setCurrentPassword(event.target.value)}
                required
              />
              <label className="auth-label" htmlFor="new-password">NEW PASSWORD</label>
              <PasswordInput
                className="auth-input"
                id="new-password"
                name="new_password"
                autoComplete="new-password"
                minLength={12}
                value={newPassword}
                onChange={(event) => setNewPassword(event.target.value)}
                required
              />
              <label className="auth-label" htmlFor="confirm-new-password">CONFIRM NEW PASSWORD</label>
              <PasswordInput
                className="auth-input"
                id="confirm-new-password"
                name="password_confirmation"
                autoComplete="new-password"
                minLength={12}
                value={confirmPassword}
                onChange={(event) => setConfirmPassword(event.target.value)}
                required
              />
              {error && <p className="form-error" role="alert">{error}</p>}
              <button className="auth-submit" type="submit" disabled={submitting}>
                {submitting ? "Updating password…" : "Update password"}
              </button>
            </form>
          </>
        )}
      </section>
    </div>
  );
}

"use client";

import { useState, type InputHTMLAttributes } from "react";

type PasswordInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, "type">;

export default function PasswordInput({ className = "", ...props }: PasswordInputProps) {
  const [visible, setVisible] = useState(false);

  return (
    <span className={`password-input-wrap ${className}`.trim()}>
      <input {...props} className="password-input" type={visible ? "text" : "password"} />
      <button
        className="password-visibility-toggle"
        type="button"
        aria-label={visible ? "Hide password" : "Show password"}
        aria-pressed={visible}
        onClick={() => setVisible((current) => !current)}
      >
        {visible ? (
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8" />
            <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c5 0 8.5 4.5 9.5 7-.4 1-1.1 2-2 2.9M6.2 6.2C3.9 7.7 2.7 9.8 2.5 12c.5 1.3 1.8 3 3.7 4.4A9.8 9.8 0 0 0 12 19c1.2 0 2.3-.2 3.3-.6" />
          </svg>
        ) : (
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
            <path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" />
            <circle cx="12" cy="12" r="2.5" />
          </svg>
        )}
      </button>
    </span>
  );
}

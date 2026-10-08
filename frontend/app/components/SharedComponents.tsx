"use client";

import type { ReactNode } from "react";
import { iconPaths } from "../lib/smartbite";

export function Icon({ name, size = 20, className = "" }: { name: string; size?: number; className?: string }) {
  return (
    <svg aria-hidden="true" className={className} width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
      <path d={iconPaths[name] ?? iconPaths.spark} />
    </svg>
  );
}

export function PageHeading({ kicker, title, subtitle, action }: { kicker: string; title: string; subtitle: string; action?: ReactNode }) {
  return <div className="management-heading"><div><div className="section-eyebrow">{kicker}</div><h1>{title}</h1><p>{subtitle}</p></div>{action}</div>;
}

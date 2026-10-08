"use client";

import { Icon } from "./SharedComponents";

export function LandingScreen({ onSignIn, notice }: { onSignIn: () => void; notice?: string }) {
  return (
    <main className="auth-page landing-page">
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
      <section className="landing-content">
        <a className="brand landing-mobile-brand" href="#" onClick={(event) => event.preventDefault()}>
          <span className="brand-mark">s<span>.</span></span>
          <span className="brand-name">Smartbite</span>
        </a>
        <div className="landing-card">
          <span className="landing-eyebrow"><span>✳</span> YOUR CAMPUS, YOUR CANTEEN</span>
          <h2>Welcome to<br />Smartbite.</h2>
          <p className="landing-intro">Order lunch ahead, keep up with your pickup, and make the most of your school day.</p>
          {notice && <p className="landing-notice" role="status">{notice}</p>}
          <button className="auth-submit landing-submit" type="button" onClick={onSignIn}>Sign in to continue <Icon name="arrow" size={17} /></button>
          <div className="landing-benefits">
            <div><span><Icon name="bag" size={16} /></span><p><strong>Order ahead</strong><small>Choose your lunch before the break.</small></p></div>
            <div><span><Icon name="clock" size={16} /></span><p><strong>Know when to pick up</strong><small>Stay up to date on your order.</small></p></div>
          </div>
          <p className="landing-school-note"><span>HNU</span> Holy Name University <i /> School canteen portal</p>
        </div>
      </section>
    </main>
  );
}

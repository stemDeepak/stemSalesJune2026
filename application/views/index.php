<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>STEM CRM · Sign In</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet"/>
<link rel="icon" href="https://stemlearning.in/wp-content/uploads/2025/07/favicon.svg" sizes="32x32" />
<link rel="icon" href="https://stemlearning.in/wp-content/uploads/2025/07/favicon.svg" sizes="192x192" />
<link rel="apple-touch-icon" href="https://stemlearning.in/wp-content/uploads/2025/07/favicon.svg" />
<meta name="msapplication-TileImage" content="https://stemlearning.in/wp-content/uploads/2025/07/favicon.svg" />
<style>
/* ══════════════════════════════════════════════
   TOKENS
══════════════════════════════════════════════ */
:root{
  --c1:#FF5C1A;   /* STEM orange */
  --c2:#FF9747;   /* warm amber  */
  --c3:#E8192C;   /* STEM red    */
  --c4:#0EA5E9;   /* sky blue    */
  --c5:#6EE7B7;   /* mint        */
  --bg:#04060E;
  --card:rgba(6,10,22,0.78);
  --border:rgba(255,92,26,0.18);
  --text:#F0F4FF;
  --muted:rgba(180,200,240,0.45);
  --font-head:'Syne',sans-serif;
  --font-body:'DM Sans',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden}
body{
  font-family:var(--font-body);
  background:var(--bg);
  display:flex;align-items:center;justify-content:center;
  min-height:100vh;
}

/* ══════════════════════════════════════════════
   CANVAS BG
══════════════════════════════════════════════ */
#bg{position:fixed;inset:0;z-index:0}

/* Vignette */
#vig{
  position:fixed;inset:0;z-index:1;pointer-events:none;
  background:radial-gradient(ellipse 80% 80% at 50% 50%,
    transparent 30%, rgba(4,6,14,.7) 70%, rgba(4,6,14,.97) 100%);
    background:
radial-gradient(
    circle at center,
    rgba(56, 189, 248, 0.18) 0%,
    rgba(59, 130, 246, 0.08) 25%,
    rgba(15, 23, 42, 0.6) 50%,
    rgba(2, 6, 23, 1) 100%
);
    background: radial-gradient(circle at center, rgba(56, 189, 248, 0.18) 0%, rgba(59, 130, 246, 0.08) 25%, rgba(15, 23, 42, 0.6) 50%, rgb(255 92 26 / 13%) 100%);
}

/* Noise grain overlay */
#grain{
  position:fixed;inset:0;z-index:2;pointer-events:none;opacity:.04;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size:200px 200px;
  animation:grainShift 1s steps(2) infinite;
}
@keyframes grainShift{
  0%{background-position:0 0}
  25%{background-position:-30px 10px}
  50%{background-position:20px -15px}
  75%{background-position:-10px 25px}
  100%{background-position:15px -5px}
}

/* ══════════════════════════════════════════════
   PAGE WRAPPER — SPLIT LAYOUT
══════════════════════════════════════════════ */
.wrap{
  position:relative;z-index:10;
  width:100%;max-width:1060px;
  min-height:100vh;
  display:flex;align-items:center;justify-content:center;
  padding:24px 20px;
  gap:0;
}

/* ── LEFT PANEL ── */
.left-panel{
  flex:1;
  padding:40px 48px 40px 32px;
  display:flex;flex-direction:column;justify-content:center;
  opacity:0;animation:panelInL .9s .1s cubic-bezier(.22,1,.36,1) forwards;
}
@keyframes panelInL{from{opacity:0;transform:translateX(-40px)}to{opacity:1;transform:translateX(0)}}

.brand-mark{
  display:inline-flex;align-items:center;gap:12px;
  margin-bottom:12px;
}
.brand-hex{
  width:44px;height:44px;
  background:linear-gradient(135deg,var(--c1),var(--c3));
  clip-path:polygon(50% 0%,93% 25%,93% 75%,50% 100%,7% 75%,7% 25%);
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-head);font-size:1.1rem;font-weight:800;color:#fff;
  box-shadow:0 0 22px rgba(255,92,26,.6);
  animation:hexSpin 12s linear infinite;
}
@keyframes hexSpin{0%{filter:hue-rotate(0deg)}100%{filter:hue-rotate(30deg)}}
.brand-name{
  font-family:var(--font-head);font-size:1.3rem;font-weight:800;
  letter-spacing:.04em;color:var(--text);
}
.brand-name span{
  background:linear-gradient(90deg,var(--c1),var(--c2));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}

.lp-eyebrow{
  font-size:.72rem;font-weight:600;letter-spacing:.22em;text-transform:uppercase;
  color:var(--c1);margin-bottom:14px;opacity:.9;
}
.lp-headline{
  font-family:var(--font-head);
  font-size:clamp(2rem,3.5vw,2.8rem);
  font-weight:800;line-height:1.12;
  color:var(--text);margin-bottom:20px;
  letter-spacing:-.01em;
}
.lp-headline em{
  font-style:normal;
  background:linear-gradient(90deg,var(--c1) 0%,var(--c2) 50%,var(--c4) 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.lp-sub{
  font-size:.9rem;font-weight:300;line-height:1.7;
  color:var(--muted);max-width:360px;margin-bottom:40px;
}

/* STAT PILLS */
.stat-row{display:flex;gap:12px;flex-wrap:wrap;}
.stat-pill{
  display:flex;align-items:center;gap:8px;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.07);
  border-radius:50px;padding:8px 16px;
  transition:all .25s;
}
.stat-pill:hover{border-color:rgba(255,92,26,.3);background:rgba(255,92,26,.06);}
.stat-dot{
  width:7px;height:7px;border-radius:50%;
  animation:dotPulse 2.5s ease-in-out infinite;
}
.dot-o{background:var(--c1);box-shadow:0 0 8px var(--c1);}
.dot-b{background:var(--c4);box-shadow:0 0 8px var(--c4);animation-delay:.8s;}
.dot-g{background:var(--c5);box-shadow:0 0 8px var(--c5);animation-delay:1.6s;}
@keyframes dotPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.6)}}
.stat-text{font-size:.74rem;font-weight:600;color:rgba(200,220,255,.65);letter-spacing:.04em;}

/* Decorative line */
.deco-line{
  width:60px;height:3px;margin:36px 0;
  background:linear-gradient(90deg,var(--c1),transparent);
  border-radius:2px;
}

/* ── RIGHT PANEL — CARD ── */
.card-panel{
  width:440px;flex-shrink:0;
  background:var(--card);
  border:1px solid var(--border);
  border-radius:28px;
  padding:46px 42px 40px;
  backdrop-filter:blur(32px) saturate(180%);
  box-shadow:
    0 0 0 1px rgba(255,92,26,.04),
    0 40px 100px rgba(0,0,0,.8),
    inset 0 1px 0 rgba(255,255,255,.06);
  position:relative;overflow:hidden;
  opacity:0;
  animation:cardIn .9s .2s cubic-bezier(.22,1,.36,1) forwards;
  background:
radial-gradient(
    circle at center,
    rgba(56, 189, 248, 0.18) 0%,
    rgba(59, 130, 246, 0.08) 25%,
    rgba(15, 23, 42, 0.6) 50%,
    rgba(2, 6, 23, 1) 100%
);
}
@keyframes cardIn{from{opacity:0;transform:translateY(28px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}

/* animated top edge */
.card-panel::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1.5px;
  background:linear-gradient(90deg,transparent 0%,var(--c1) 30%,var(--c2) 50%,var(--c4) 70%,transparent 100%);
  animation:edgeShimmer 4s ease-in-out infinite;
}
@keyframes edgeShimmer{
  0%,100%{opacity:.5}
  50%{opacity:1;filter:drop-shadow(0 0 6px var(--c1))}
}

/* inner glow blob */
.card-panel::after{
  content:'';position:absolute;
  width:280px;height:280px;border-radius:50%;
  background:radial-gradient(circle,rgba(255,92,26,.07) 0%,transparent 70%);
  top:-80px;right:-80px;pointer-events:none;
  animation:blobPulse 6s ease-in-out infinite alternate;
}
@keyframes blobPulse{from{transform:scale(1)}to{transform:scale(1.2)}}

/* scan line */
.scan-line{
  position:absolute;left:0;right:0;height:1px;pointer-events:none;z-index:1;
  background:linear-gradient(90deg,transparent,rgba(255,147,64,.5),transparent);
  animation:scanDown 6s linear infinite;
}
@keyframes scanDown{
  0%{top:0;opacity:0}4%{opacity:.8}96%{opacity:.4}100%{top:100%;opacity:0}
}

/* corner brackets */
.brk{position:absolute;width:22px;height:22px;}
.brk-tl{top:14px;left:14px;border-top:1.5px solid var(--c1);border-left:1.5px solid var(--c1);border-radius:5px 0 0 0;}
.brk-tr{top:14px;right:14px;border-top:1.5px solid var(--c4);border-right:1.5px solid var(--c4);border-radius:0 5px 0 0;}
.brk-bl{bottom:14px;left:14px;border-bottom:1.5px solid var(--c4);border-left:1.5px solid var(--c4);border-radius:0 0 0 5px;}
.brk-br{bottom:14px;right:14px;border-bottom:1.5px solid var(--c1);border-right:1.5px solid var(--c1);border-radius:0 0 5px 0;}

/* ── CARD HEADER ── */
.card-head{
  margin-bottom:32px;position:relative;z-index:2;
  animation:fadeU .6s .4s both;
}
.card-logo-row{
  display:flex;align-items:center;gap:11px;margin-bottom:18px;
}
.card-hex{
  width:38px;height:38px;
  background:linear-gradient(135deg,var(--c1),var(--c3));
  clip-path:polygon(50% 0%,93% 25%,93% 75%,50% 100%,7% 75%,7% 25%);
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-head);font-size:.95rem;font-weight:800;color:#fff;
  box-shadow:0 0 16px rgba(255,92,26,.5);
  flex-shrink:0;
}
.card-brand{
  font-family:var(--font-head);font-size:1rem;font-weight:800;
  letter-spacing:.06em;color:var(--text);
}
.card-brand small{
  display:block;font-size:.6rem;font-weight:500;
  letter-spacing:.2em;color:var(--muted);margin-top:1px;
  text-transform:uppercase;
}
.card-title{
  font-family:var(--font-head);font-size:1.45rem;font-weight:700;
  color:var(--text);letter-spacing:-.01em;margin-bottom:4px;
}
.card-sub{font-size:.8rem;color:var(--muted);font-weight:300;}

/* ── FORM ── */
.form-area{position:relative;z-index:2;}

.fg{margin-bottom:16px;position:relative;}

.fl{
  display:block;font-size:.66rem;font-weight:700;
  letter-spacing:.18em;text-transform:uppercase;
  color:rgba(255,147,64,.75);margin-bottom:7px;
}

.fi-wrap{position:relative;display:flex;align-items:center;}

.fi-ico{
  position:absolute;left:13px;z-index:2;
  color:rgba(255,147,64,.4);
  display:flex;align-items:center;
  pointer-events:none;transition:color .3s;
}

.fi{
  width:100%;
  background:rgba(255,255,255,.03);
  border:1.5px solid rgba(255,92,26,.12);
  border-radius:14px;
  color:var(--text);
  font-family:var(--font-body);font-size:.88rem;font-weight:400;
  padding:11px 16px 11px 40px;
  outline:none;
  transition:border-color .3s,background .3s,box-shadow .3s;
  letter-spacing:.02em;
}
.fi::placeholder{color:var(--muted);}
.fi:focus{
  border-color:var(--c1);
  background:rgba(255,92,26,.05);
  box-shadow:0 0 0 3px rgba(255,92,26,.1),0 0 24px rgba(255,92,26,.2);
}
.fi-wrap:focus-within .fi-ico{color:var(--c2);}

/* float label effect */
.fi:focus + .fi-label-float,
.fi:not(:placeholder-shown) + .fi-label-float{
  transform:translateY(-22px) scale(.78);
  color:var(--c2);
}

.pw-btn{
  position:absolute;right:12px;background:none;border:none;
  color:var(--muted);cursor:pointer;padding:4px;
  display:flex;align-items:center;transition:color .25s;
}
.pw-btn:hover{color:var(--c2);}

/* ── EXTRAS ── */
.extras{
  display:flex;align-items:center;justify-content:space-between;
  margin:8px 0 22px;
  animation:fadeU .6s .62s both;
}
.chk-wrap{display:flex;align-items:center;gap:7px;cursor:pointer;user-select:none;}
.chk-wrap input{display:none;}
.chk-box{
  width:15px;height:15px;border-radius:4px;flex-shrink:0;
  border:1.5px solid rgba(255,92,26,.28);
  background:rgba(255,92,26,.04);
  display:flex;align-items:center;justify-content:center;
  transition:all .22s;
}
.chk-wrap input:checked + .chk-box{
  background:var(--c1);border-color:var(--c1);
  box-shadow:0 0 10px rgba(255,92,26,.5);
}
.chk-box::after{
  content:'';width:3.5px;height:6.5px;
  border:2px solid #fff;border-top:none;border-left:none;
  transform:rotate(44deg) translate(-1px,-1px);
  opacity:0;transition:opacity .18s;
}
.chk-wrap input:checked + .chk-box::after{opacity:1;}
.chk-txt{font-size:.75rem;color:var(--muted);}
.frgt{font-size:.75rem;color:var(--c2);text-decoration:none;opacity:.7;transition:opacity .25s;}
.frgt:hover{opacity:1;text-shadow:0 0 10px rgba(255,147,64,.5);}

/* ── CTA BUTTON ── */
.btn-cta{
  width:100%;padding:13px;
  background:linear-gradient(135deg,var(--c1) 0%,var(--c3) 100%);
  border:none;border-radius:14px;
  color:#fff;font-family:var(--font-head);
  font-size:.8rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
  cursor:pointer;position:relative;overflow:hidden;
  box-shadow:0 6px 28px rgba(232,25,44,.3),0 0 0 1px rgba(255,92,26,.2);
  transition:transform .2s,box-shadow .3s;
  animation:fadeU .6s .68s both;
}
.btn-cta::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,.2),transparent 60%);
  opacity:0;transition:opacity .3s;
}
.btn-cta:hover{
  transform:translateY(-2px);
  box-shadow:0 12px 40px rgba(232,25,44,.45),0 0 30px rgba(255,92,26,.3);
}
.btn-cta:hover::before{opacity:1;}
.btn-cta:active{transform:translateY(0);}
/* shimmer sweep */
.btn-cta::after{
  content:'';position:absolute;top:0;left:-120%;width:55%;height:100%;
  background:linear-gradient(120deg,transparent,rgba(255,255,255,.3),transparent);
  transition:left .55s;
}
.btn-cta:hover::after{left:150%;}
/* loading */
.btn-cta .bt{transition:opacity .2s;}
.btn-cta .bsp{position:absolute;inset:0;display:none;align-items:center;justify-content:center;}
.btn-cta.ld .bt{opacity:0;}
.btn-cta.ld .bsp{display:flex;}
.sp-ring{width:20px;height:20px;border:2.5px solid rgba(255,255,255,.2);border-top-color:#fff;border-radius:50%;animation:spinR .65s linear infinite;}
@keyframes spinR{to{transform:rotate(360deg)}}

/* ── DIVIDER ── */
.div-row{
  display:flex;align-items:center;gap:10px;margin:18px 0;
  animation:fadeU .6s .74s both;
}
.div-l{flex:1;height:1px;background:rgba(255,92,26,.1);}
.div-t{font-size:.65rem;color:var(--muted);letter-spacing:.14em;white-space:nowrap;}

/* ── SOCIAL ── */
.soc-grid{
  display:grid;grid-template-columns:repeat(3,1fr);gap:8px;
  animation:fadeU .6s .78s both;
}
.btn-s{
  padding:9px 6px;
  background:rgba(255,255,255,.03);
  border:1.5px solid rgba(255,255,255,.07);
  border-radius:11px;
  color:var(--muted);font-family:var(--font-body);
  font-size:.72rem;font-weight:600;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;
  transition:all .22s;letter-spacing:.03em;
}
.btn-s:hover{
  border-color:rgba(255,92,26,.3);color:var(--text);
  background:rgba(255,92,26,.05);transform:translateY(-1px);
}
.btn-s svg{width:14px;height:14px;flex-shrink:0;}

/* ── FOOTER ── */
.card-foot{
  text-align:center;margin-top:18px;font-size:.73rem;color:var(--muted);
  position:relative;z-index:2;
  animation:fadeU .6s .82s both;
}
.card-foot a{color:var(--c2);text-decoration:none;font-weight:600;transition:opacity .22s;}
.card-foot a:hover{opacity:.7;}

/* ── TOAST ── */
#toast{
  position:absolute;top:-54px;left:50%;transform:translateX(-50%);
  padding:8px 18px;border-radius:10px;font-size:.75rem;white-space:nowrap;
  opacity:0;pointer-events:none;transition:opacity .3s,top .3s;font-weight:600;z-index:10;
}
#toast.err{background:rgba(232,25,44,.15);border:1px solid rgba(232,25,44,.4);color:#ff8a92;}
#toast.ok {background:rgba(0,210,120,.1);border:1px solid rgba(0,210,120,.35);color:#7cffbc;}
#toast.show{opacity:1;top:-50px;}

/* ── ANIMATIONS ── */
@keyframes fadeU{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

/* ── RESPONSIVE ── */
@media(max-width:820px){
  .left-panel{display:none;}
  .card-panel{width:100%;max-width:430px;}
  .wrap{padding:20px 16px;}
}
@media(max-width:480px){
  .card-panel{padding:36px 22px 30px;}
}

/* ── CUSTOM CURSOR ── */
.cur{
  position:fixed;width:10px;height:10px;border-radius:50%;
  background:var(--c1);pointer-events:none;z-index:9999;
  transform:translate(-50%,-50%);
  box-shadow:0 0 12px var(--c1);
  transition:transform .1s,width .25s,height .25s;
}
.cur2{
  position:fixed;width:32px;height:32px;border-radius:50%;
  border:1px solid rgba(255,92,26,.45);pointer-events:none;z-index:9998;
  transform:translate(-50%,-50%);
  transition:all .2s ease-out;
}
/* STEM CRM Custom Alert – Glassmorphic + Orange Accent */
.stem-alert {
    position: relative;
    background: rgba(255, 92, 26, 0.12);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 92, 26, 0.35);
    border-radius: 18px;
    padding: 1rem 1.2rem;
    margin-bottom: 1.5rem;
    color: #FFD5C2;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    font-weight: 500;
    letter-spacing: 0.02em;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    animation: stemAlertSlide 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.stem-alert::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #FF5C1A, #E8192C);
    border-radius: 4px 0 0 4px;
}

.stem-alert-error {
    background: rgba(232, 25, 44, 0.12);
    border-color: rgba(232, 25, 44, 0.5);
    color: #FFB3BB;
}

.stem-alert-error::before {
    background: #E8192C;
}

.stem-alert-success {
    background: rgba(110, 231, 183, 0.12);
    border-color: rgba(110, 231, 183, 0.4);
    color: #C0FFE0;
}

.stem-alert-success::before {
    background: #6EE7B7;
}

/* Close button */
.stem-alert-close {
    background: none;
    border: none;
    color: currentColor;
    opacity: 0.6;
    font-size: 1.2rem;
    line-height: 1;
    cursor: pointer;
    padding: 0 6px;
    transition: all 0.2s ease;
    font-weight: 300;
    border-radius: 30px;
    width: 26px;
    height: 26px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.stem-alert-close:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 0.1);
    transform: scale(1.08);
}

/* Icon inside alert (optional) */
.stem-alert-icon {
    margin-right: 10px;
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
}

/* Animation */
@keyframes stemAlertSlide {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Fade out when dismissed */
.stem-alert.fade-out {
    animation: stemAlertFadeOut 0.25s forwards;
}

@keyframes stemAlertFadeOut {
    to {
        opacity: 0;
        transform: translateY(-10px);
        display: none;
    }
}
.stem-alert::before {
    width: 0px;
}
</style>
</head>
<body>

<!-- Custom cursor -->
<div class="cur" id="cur"></div>
<div class="cur2" id="cur2"></div>

<!-- BG canvas -->
<canvas id="bg"></canvas>
<div id="vig"></div>
<div id="grain"></div>


<!-- ══ PAGE ══ -->
<div class="wrap">

  <!-- LEFT PANEL -->
  <div class="left-panel">
    <div class="brand-mark">
      <div class="brand-hex">S</div>
      <div class="brand-name"><span>STEM</span> CRM</div>
    </div>

    <div class="lp-eyebrow">Intelligent Workspace Platform</div>
    <h1 class="lp-headline">
      Building<br/>
      <em>Brains</em><br/>
      Beyond Books.
    </h1>
    <p class="lp-sub">
      India's most powerful CRM built for education.
      Manage schools, students & comapnies — all from one unified dashboard.
    </p>

    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-dot dot-o"></span>
        <span class="stat-text">20L+ Students</span>
      </div>
      <div class="stat-pill">
        <span class="stat-dot dot-o"></span>
        <span class="stat-text">30K+ Teachers</span>
      </div>
      <div class="stat-pill">
        <span class="stat-dot dot-b"></span>
        <span class="stat-text">5.3K+ Schools</span>
      </div>
      <div class="stat-pill">
        <span class="stat-dot dot-g"></span>
        <span class="stat-text">26 States</span>
      </div>
      <div class="stat-pill">
        <span class="stat-dot dot-g"></span>
        <span class="stat-text">350+ of India’s Top Corporates</span>
      </div>
    </div>

    <div class="deco-line"></div>

    <div style="font-size:.7rem;color:var(--muted);letter-spacing:.12em;text-transform:uppercase;font-weight:600;">
      STEM Learning Pvt Ltd &nbsp;·&nbsp;
    </div>
  </div>

  <!-- CARD -->
  <div class="card-panel" id="card">
    <div class="scan-line"></div>
    <div class="brk brk-tl"></div>
    <div class="brk brk-tr"></div>
    <div class="brk brk-bl"></div>
    <div class="brk brk-br"></div>
    <div id="toast"></div>

    <!-- Header -->
    <div class="card-head">
      <div class="card-logo-row">
        <div class="card-hex">S</div>
        <div class="card-brand">
          STEM CRM
          <small>Workspace Portal</small>
        </div>
      </div>
      <div class="card-title">Welcome back</div>
      <div class="card-sub">Sign in to continue to your dashboard</div>
    </div>

    <!-- SIGN IN FORM (wrapped to remove console warning & improve semantics) -->
    <form id="signinForm" action="<?= base_url('Menu/login'); ?>" method="post">
      <div class="form-area">
        <div class="fg" style="animation:fadeU .6s .48s both">
          <label class="fl" for="uname">Username</label>
          <div class="fi-wrap">
            <span class="fi-ico">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4z"/>
              </svg>
            </span>
            <input class="fi" type="text" id="uname" name="user" placeholder="Enter your username" autocomplete="username" />
          </div>
        </div>

        <div class="fg" style="animation:fadeU .6s .54s both">
          <label class="fl" for="pwd">Password</label>
          <div class="fi-wrap">
            <span class="fi-ico">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
              </svg>
            </span>
            <input class="fi" type="password" id="pwd" name="password" placeholder="Enter your password" autocomplete="current-password" />
            <button class="pw-btn" id="pwBtn" type="button" aria-label="Toggle password">
              <svg id="eyeSvg" xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- STEM CRM Custom Flash Alert -->
        <?php if ($this->session->flashdata('success_message')): ?>
            <div class="stem-alert stem-alert-success" id="stemFlashAlert">
                <div style="display: flex; align-items: center;">
                    <span class="stem-alert-icon">✓</span>
                    <span><?= htmlspecialchars($this->session->flashdata('success_message')); ?></span>
                </div>
                <button type="button" class="stem-alert-close" aria-label="Close">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($this->session->flashdata('error_message')): ?>
            <div class="stem-alert stem-alert-error" id="stemFlashAlert">
                <div style="display: flex; align-items: center;">
                    <span class="stem-alert-icon">⚠️</span>
                    <span><?= htmlspecialchars($this->session->flashdata('error_message')); ?></span>
                </div>
                <button type="button" class="stem-alert-close" aria-label="Close">&times;</button>
            </div>
        <?php endif; ?>

        <div class="extras">
          <label class="chk-wrap">
            <input type="checkbox" id="rem" name="remember"/>
            <span class="chk-box"></span>
            <span class="chk-txt">Remember me</span>
          </label>
          <a href="javascript:void(0)" class="frgt">Forgot password?</a>
        </div>

        <button class="btn-cta" id="signBtn" type="submit">
          <span class="bt">Sign In</span>
          <span class="bsp"><span class="sp-ring"></span></span>
        </button>

        <div class="div-row">
          <div class="div-l"></div>
          <span class="div-t">STEM Learning Pvt Ltd</span>
          <div class="div-l"></div>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
/* ══════════════════════════════════════════
   CANVAS BACKGROUND — animated galaxy mesh
   FIXED: drawMesh variable reference (n → a)
══════════════════════════════════════════ */
(function(){
  const cv = document.getElementById('bg');
  const cx = cv.getContext('2d');
  let W, H, stars=[], nodes=[], t=0;

  function resize(){
    W = cv.width  = innerWidth;
    H = cv.height = innerHeight;
    initScene();
  }

  function initScene(){
    /* Stars */
    stars = Array.from({length:260}, ()=>({
      x:Math.random()*W, y:Math.random()*H,
      r:Math.random()*1.6+.2,
      a:Math.random()*.8+.1,
      spd:Math.random()*.15+.03,
      tw:Math.random()*Math.PI*2,
      tws:.004+Math.random()*.01,
      hue:Math.random()<.7?28:200
    }));

    /* Mesh nodes */
    nodes = Array.from({length:55}, ()=>({
      x:Math.random()*W, y:Math.random()*H,
      vx:(Math.random()-.5)*.35,
      vy:(Math.random()-.5)*.35,
      r:Math.random()*2+1
    }));
  }

  /* Aurora bands */
  function drawAurora(){
    const bands = [
      {y:.2, c1:'rgba(255,92,26,.045)', c2:'rgba(232,25,44,.035)'},
      {y:.55, c1:'rgba(14,165,233,.03)', c2:'rgba(110,231,183,.025)'},
      {y:.8,  c1:'rgba(255,92,26,.025)', c2:'transparent'},
    ];
    bands.forEach(b=>{
      const y = H*b.y + Math.sin(t*.0007)*30;
      const g = cx.createLinearGradient(0,y-120,0,y+120);
      g.addColorStop(0,'transparent');
      g.addColorStop(.5,b.c1);
      g.addColorStop(1,b.c2);
      cx.fillStyle = g;
      cx.fillRect(0, y-120, W, 240);
    });
  }

  /* Shooting star */
  let shots = [];
  function spawnShot(){
    if(Math.random()<.004){
      shots.push({
        x:Math.random()*W*.6, y:Math.random()*H*.4,
        len:80+Math.random()*120,
        spd:8+Math.random()*6,
        angle:Math.PI/4+Math.random()*.3,
        a:1, life:1
      });
    }
  }
  function drawShots(){
    shots = shots.filter(s=>s.a>0);
    shots.forEach(s=>{
      cx.save();
      cx.globalAlpha = s.a;
      const g = cx.createLinearGradient(s.x,s.y,s.x+Math.cos(s.angle)*s.len,s.y+Math.sin(s.angle)*s.len);
      g.addColorStop(0,'rgba(255,200,140,0)');
      g.addColorStop(.4,'rgba(255,200,140,.8)');
      g.addColorStop(1,'rgba(255,220,180,0)');
      cx.strokeStyle = g; cx.lineWidth = 1.5;
      cx.beginPath();
      cx.moveTo(s.x, s.y);
      cx.lineTo(s.x+Math.cos(s.angle)*s.len, s.y+Math.sin(s.angle)*s.len);
      cx.stroke(); cx.restore();
      s.x += Math.cos(s.angle)*s.spd;
      s.y += Math.sin(s.angle)*s.spd;
      s.a -= .025;
    });
  }

  /* FIXED: Mesh draw — corrected variable usage */
  function drawMesh(){
    // update positions
    nodes.forEach(n => {
      n.x += n.vx; n.y += n.vy;
      if(n.x<0||n.x>W) n.vx*=-1;
      if(n.y<0||n.y>H) n.vy*=-1;
    });
    // draw connections
    nodes.forEach((a,i)=>{
      nodes.slice(i+1).forEach(b=>{
        const dx=a.x-b.x, dy=a.y-b.y;
        const d=Math.sqrt(dx*dx+dy*dy);
        if(d<140){
          cx.beginPath();
          cx.moveTo(a.x,a.y); cx.lineTo(b.x,b.y);
          const alpha = (1-d/140)*.12;
          cx.strokeStyle=`rgba(255,92,26,${alpha})`;
          cx.lineWidth=.6; cx.stroke();
        }
      });
      // Node dot - FIXED: use 'a' instead of undefined 'n'
      cx.beginPath(); cx.arc(a.x, a.y, a.r, 0, Math.PI*2);
      cx.fillStyle='rgba(255,147,64,.35)'; cx.fill();
    });
  }

  /* Stars */
  function drawStars(){
    stars.forEach(s=>{
      s.tw += s.tws;
      const a = s.a*(.4+.6*Math.sin(s.tw));
      cx.beginPath(); cx.arc(s.x,s.y,s.r,0,Math.PI*2);
      cx.fillStyle = s.hue===28
        ? `rgba(255,200,140,${a})`
        : `rgba(140,200,255,${a})`;
      cx.fill();
      s.y += s.spd; if(s.y>H){s.y=0;s.x=Math.random()*W;}
    });
  }

  /* Orbit rings (decorative) */
  function drawOrbit(){
    const cx2 = cx;
    [[W*.72,H*.3,160,0],[W*.22,H*.72,90,Math.PI*.3]].forEach(([ox,oy,r,phase])=>{
      cx2.save();
      cx2.translate(ox,oy);
      cx2.rotate(phase+t*.0002);
      cx2.beginPath();
      cx2.ellipse(0,0,r,r*.35,0,0,Math.PI*2);
      const g=cx2.createLinearGradient(-r,0,r,0);
      g.addColorStop(0,'rgba(255,92,26,0)');
      g.addColorStop(.5,'rgba(255,92,26,.12)');
      g.addColorStop(1,'rgba(255,92,26,0)');
      cx2.strokeStyle=g; cx2.lineWidth=1; cx2.stroke();
      /* dot on orbit */
      const a2=t*.001;
      const dx=Math.cos(a2)*r, dy=Math.sin(a2)*r*.35;
      cx2.beginPath(); cx2.arc(dx,dy,3,0,Math.PI*2);
      cx2.fillStyle='rgba(255,147,64,.7)'; cx2.fill();
      cx2.restore();
    });
  }

  function frame(){
    t++;
    cx.clearRect(0,0,W,H);
    /* Deep space gradient */
    const bg=cx.createRadialGradient(W*.5,H*.45,.1,W*.5,H*.45,W*.8);
    bg.addColorStop(0,'#080e20');
    bg.addColorStop(.5,'#050a16');
    bg.addColorStop(1,'#020510');
    cx.fillStyle=bg; cx.fillRect(0,0,W,H);
    drawAurora();
    drawOrbit();
    drawMesh();
    drawStars();
    spawnShot(); drawShots();
    requestAnimationFrame(frame);
  }

  resize(); frame();
  window.addEventListener('resize', resize);
})();

/* ══════════════════════════════════════════
   CUSTOM CURSOR
══════════════════════════════════════════ */
(function(){
  const c1=document.getElementById('cur'), c2=document.getElementById('cur2');
  let mx=0,my=0,lx=0,ly=0;
  document.addEventListener('mousemove',e=>{mx=e.clientX;my=e.clientY;c1.style.left=mx+'px';c1.style.top=my+'px';});
  function lerp(a,b,t){return a+(b-a)*t;}
  (function loop(){lx=lerp(lx,mx,.12);ly=lerp(ly,my,.12);c2.style.left=lx+'px';c2.style.top=ly+'px';requestAnimationFrame(loop);})();
  document.querySelectorAll('button,a,input,.chk-wrap').forEach(el=>{
    el.addEventListener('mouseenter',()=>{c1.style.transform='translate(-50%,-50%) scale(1.8)';c2.style.transform='translate(-50%,-50%) scale(1.4)';});
    el.addEventListener('mouseleave',()=>{c1.style.transform='translate(-50%,-50%) scale(1)';c2.style.transform='translate(-50%,-50%) scale(1)';});
  });
})();

/* ══════════════════════════════════════════
   3D CARD PARALLAX
══════════════════════════════════════════ */
(function(){
  const card = document.getElementById('card');
  let tick=false;
  document.addEventListener('mousemove',e=>{
    if(tick) return; tick=true;
    requestAnimationFrame(()=>{
      const cx=innerWidth/2, cy=innerHeight/2;
      const rx=(e.clientY-cy)/cy*4, ry=-(e.clientX-cx)/cx*4;
      card.style.transition='transform .08s ease';
      card.style.transform=`perspective(1200px) rotateX(${rx}deg) rotateY(${ry}deg)`;
      tick=false;
    });
  });
  document.addEventListener('mouseleave',()=>{
    card.style.transition='transform .8s ease';
    card.style.transform='perspective(1200px) rotateX(0) rotateY(0)';
  });
})();

/* ══════════════════════════════════════════
   PASSWORD TOGGLE
══════════════════════════════════════════ */
document.getElementById('pwBtn').addEventListener('click',function(){
  const inp=document.getElementById('pwd');
  const svg=document.getElementById('eyeSvg');
  if(inp.type==='password'){
    inp.type='text';
    svg.innerHTML=`<path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/><path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>`;
  } else {
    inp.type='password';
    svg.innerHTML=`<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>`;
  }
});

/* ══════════════════════════════════════════
   SIGN IN (non-blocking with form validation)
══════════════════════════════════════════ */
function toast(msg,type){
  const t=document.getElementById('toast');
  t.textContent=msg; t.className=type+' show';
  setTimeout(()=>t.classList.remove('show'),3200);
}

// const form = document.getElementById('signinForm');
// form.addEventListener('submit', function(e){
//   e.preventDefault();
//   const u=document.getElementById('uname').value.trim();
//   const p=document.getElementById('pwd').value;
//   const btn=document.getElementById('signBtn');
//   if(!u||!p){
//     toast('⚠  Please enter both username and password.','err');
//     document.getElementById(!u?'uname':'pwd').focus();
//     return;
//   }
//   btn.classList.add('ld'); btn.disabled=true;
//   setTimeout(()=>{
//     btn.classList.remove('ld'); btn.disabled=false;
//     toast('✓  Signed in — welcome back!','ok');
//     // optional: redirect simulation
//   }, 2000);
// });

// Enter key triggers submit via form behavior already fine
</script>
<script>
    // Auto-dismiss after 5 seconds + smooth close button
    document.querySelectorAll('.stem-alert').forEach(alert => {
        const closeBtn = alert.querySelector('.stem-alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                alert.classList.add('fade-out');
                setTimeout(() => alert.remove(), 300);
            });
        }
        setTimeout(() => {
            if (alert && alert.parentNode) {
                alert.classList.add('fade-out');
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    });
</script>
</body>
</html>
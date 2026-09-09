<?php
require '../../main.inc.php';
require_once __DIR__.'/lib/access.lib.php';
header('Cache-Control: no-store');
if (empty($user->id) || !empty($user->socid) || empty($conf->puchatyzakatek->enabled) || !pz_can_read()) accessforbidden();
$pzName=trim($user->firstname.' '.$user->lastname) ?: $user->login;
$pzBoot=array('userId'=>(int)$user->id,'manage'=>(bool)pz_can_manage(),'token'=>newToken(),'storageKey'=>'pz_pending_'.((int)$conf->entity).'_'.((int)$user->id),'admin'=>(bool)$user->admin,'write'=>(bool)pz_can_write(),'clientCardUrl'=>DOL_URL_ROOT.'/societe/card.php',
'clientUrl'=>DOL_URL_ROOT.'/societe/card.php?action=create&customer=1&backtopage='.urlencode($_SERVER['SCRIPT_NAME']));
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Puchaty Zakątek</title>
<link rel="icon" type="image/png" href="<?php echo DOL_URL_ROOT; ?>/custom/puchatyzakatek/img/logo-transparent.png?v=2" />
<style>
  :root{
    --bg:#fffaf7;
    --panel:#ffffff;
    --panel2:#fff3ef;
    --mint:#cfe9df;
    --mint2:#eaf7f2;
    --lilac:#dcd3ef;
    --lilac2:#f3effb;
    --peach:#f6d2bf;
    --peach2:#fff0e8;
    --rose:#e89bad;
    --rose2:#f8dce3;
    --ink:#2d2a2a;
    --muted:#7a7171;
    --line:#eadfda;
    --ok:#55a87b;
    --warn:#d79c3b;
    --danger:#d86d74;
    --shadow:0 10px 30px rgba(111,83,77,.09);
    --radius:22px;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;background:var(--bg);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
  button,input,select,textarea{font:inherit}
  button{cursor:pointer}
  .app{min-height:100vh;display:grid;grid-template-columns:270px 1fr}
  .sidebar{
    position:sticky;top:0;height:100vh;padding:18px 14px;background:linear-gradient(180deg,#fff4ef 0%,#fffaf7 54%,#f4f0fb 100%);
    border-right:1px solid var(--line);display:flex;flex-direction:column;gap:16px
  }
  .brand{
    background:rgba(255,255,255,.78);border:1px solid var(--line);border-radius:28px;padding:18px 14px;text-align:center;box-shadow:var(--shadow)
  }
  .logo-mark{width:96px;height:96px;margin:0 auto 10px;background:transparent;border:0;box-shadow:none}
  .logo-mark img{width:100%;height:100%;object-fit:contain;display:block}
  .brand h1{font-family:Georgia,serif;font-style:italic;margin:2px 0 0;font-size:24px}
  .brand small{color:var(--muted);font-size:10px;letter-spacing:.12em}
  .nav{display:flex;flex-direction:column;gap:7px}
  .nav button{
    border:0;background:transparent;color:var(--ink);display:flex;align-items:center;gap:11px;padding:12px 14px;border-radius:16px;text-align:left;font-weight:700
  }
  .nav button:hover{background:#fff}
  .nav button.active{background:linear-gradient(90deg,var(--rose2),#fff);box-shadow:inset 0 0 0 1px #f2c3ce}
  .nav .ico{width:24px;text-align:center;font-size:18px}
  .sidebar-foot{margin-top:auto;background:#fff;border:1px solid var(--line);padding:12px;border-radius:16px;color:var(--muted);font-size:12px}
  .main{min-width:0}
  .topbar{
    height:72px;padding:0 24px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line);
    background:rgba(255,250,247,.92);backdrop-filter:blur(14px);position:sticky;top:0;z-index:20
  }
  .topbar h2{margin:0;font-size:22px}
  .top-actions{display:flex;align-items:center;gap:10px}
  .pill{border:1px solid var(--line);background:#fff;border-radius:999px;padding:9px 13px;font-size:13px}
  .avatar{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:var(--rose);color:white;font-weight:800}
  .content{padding:24px}
  .view{display:none}
  .view.active{display:block}
  .grid{display:grid;gap:18px}
  .grid-2{grid-template-columns:1.4fr .9fr}
  .grid-3{grid-template-columns:repeat(3,1fr)}
  .card{
    background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px
  }
  .card h3{margin:0 0 14px;font-size:18px}
  .muted{color:var(--muted)}
  .section-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
  .section-title h3{margin:0}
  .btn{border:0;border-radius:14px;padding:10px 14px;font-weight:800}
  .btn.primary{background:var(--rose);color:#fff}
  .btn.soft{background:var(--rose2);color:#7d4050}
  .btn.mint{background:var(--mint);color:#355f50}
  .btn.lilac{background:var(--lilac);color:#5e527b}
  .btn.ghost{background:#fff;border:1px solid var(--line)}
  .btn.danger{background:#fde7e8;color:#a4464b}
  .input,.select,.textarea{
    width:100%;border:1px solid var(--line);background:#fff;border-radius:14px;padding:11px 12px;outline:none
  }
  .input:focus,.select:focus,.textarea:focus{border-color:#e7a4b4;box-shadow:0 0 0 3px #fde8ee}
  .services{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
  .service{
    border:1px solid var(--line);background:linear-gradient(180deg,#fff,#fff8f5);border-radius:18px;padding:14px;transition:.18s;min-height:120px
  }
  .service:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(100,75,75,.10)}
  .service .dog{font-size:30px}
  .service b{display:block;margin-top:8px}
  .service strong{display:block;margin-top:8px;font-size:20px;color:#8d4050}
  .addons{display:grid;grid-template-columns:repeat(6,1fr);gap:10px}
  .addon{border:1px solid var(--line);border-radius:16px;padding:12px;background:var(--mint2);text-align:center}
  .addon b{display:block}
  .checkout{position:sticky;top:92px}
  .person{padding:12px;border:1px solid var(--line);border-radius:16px;background:#fffaf8;margin-bottom:10px}
  .person b{display:block}
  .cart{width:100%;border-collapse:collapse;margin-top:8px}
  .cart th,.cart td{padding:10px 6px;border-bottom:1px solid var(--line);text-align:left;font-size:13px}
  .cart th:last-child,.cart td:last-child{text-align:right}
  .sum{display:flex;justify-content:space-between;align-items:center;padding:14px 0;font-size:20px;font-weight:900}
  .paygrid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
  .pay{border:1px solid var(--line);background:#fff;border-radius:14px;padding:10px;text-align:center;font-weight:700}
  .pay.active{background:var(--rose2);border-color:#e9a9b8}
  .finish{display:grid;grid-template-columns:1fr 1.2fr;gap:8px;margin-top:12px}
  .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
  .kpi{padding:16px;border-radius:18px;border:1px solid var(--line);background:#fff}
  .kpi span{display:block;color:var(--muted);font-size:12px}
  .kpi strong{display:block;font-size:26px;margin-top:4px}
  .progress{height:12px;border-radius:999px;background:#eee7e4;overflow:hidden}
  .progress > div{height:100%;background:linear-gradient(90deg,var(--mint),var(--rose));width:44.6%}
  .table{width:100%;border-collapse:collapse}
  .table th,.table td{padding:12px 10px;border-bottom:1px solid var(--line);text-align:left;font-size:13px}
  .table th{color:var(--muted);font-weight:700}
  .tag{display:inline-block;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800}
  .tag.mint{background:var(--mint2);color:#3e6f5d}
  .tag.lilac{background:var(--lilac2);color:#67567f}
  .tag.peach{background:var(--peach2);color:#8c6046}
  .tag.rose{background:var(--rose2);color:#844557}
  .calendar{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
  .day{background:#fff;border:1px solid var(--line);border-radius:18px;padding:12px;min-height:340px}
  .day h4{margin:0 0 12px}
  .appt{border-left:4px solid var(--rose);background:#fff7f8;padding:9px;border-radius:12px;margin:8px 0;font-size:12px}
  .appt.mint{border-left-color:#67b18d;background:#f1faf6}
  .appt.lilac{border-left-color:#9886c4;background:#f7f4fd}
  .chart{height:220px;display:flex;align-items:end;gap:14px;padding:12px 4px 4px}
  .bar{flex:1;background:linear-gradient(180deg,var(--rose),var(--peach));border-radius:10px 10px 4px 4px;min-width:20px;position:relative}
  .bar span{position:absolute;bottom:-22px;left:50%;transform:translateX(-50%);font-size:11px;color:var(--muted)}
  .bar i{position:absolute;top:-22px;left:50%;transform:translateX(-50%);font-style:normal;font-size:11px;font-weight:800}
  .formgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
  .field label{display:block;font-size:12px;color:var(--muted);margin:0 0 6px}
  .notice{padding:12px 14px;border-radius:14px;background:#fff7dc;border:1px solid #f0dc9f;color:#796327;font-size:13px}
  .receiptonly{display:none}
  @media (max-width:1200px){
    .app{grid-template-columns:230px 1fr}
    .services{grid-template-columns:repeat(3,1fr)}
    .addons{grid-template-columns:repeat(3,1fr)}
    .grid-2{grid-template-columns:1fr}
    .checkout{position:static}
    .calendar{grid-template-columns:repeat(2,1fr)}
  }
  @media (max-width:780px){
    .app{display:block}
    .sidebar{position:static;height:auto;border-right:0}
    .nav{display:grid;grid-template-columns:repeat(4,1fr)}
    .nav button{font-size:0;justify-content:center}
    .nav .ico{font-size:20px}
    .sidebar-foot{display:none}
    .services{grid-template-columns:repeat(2,1fr)}
    .kpis{grid-template-columns:repeat(2,1fr)}
    .calendar{grid-template-columns:1fr}
    .topbar{position:static}
  }
  @media print{
    body *{visibility:hidden}
    #receipt,#receipt *{visibility:visible}
    #receipt{display:block;position:absolute;left:0;top:0;width:80mm;padding:5mm;font-family:Arial,sans-serif;color:#000;background:#fff}
    #receipt h2{text-align:center}
    #receipt table{width:100%;border-collapse:collapse}
    #receipt td{padding:3px 0;border-bottom:1px dashed #aaa;font-size:11px}
  }

  .pos-stepper{display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap}
  .step-pill{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--muted);font-weight:800;font-size:13px}
  .step-pill.active{background:linear-gradient(90deg,var(--rose2),var(--mint2));color:var(--ink);border-color:#e9b7c3}
  .step-pill.done{background:var(--mint2);color:#3e6f5d}
  .client-search-wrap{max-width:720px;margin:0 auto}
  .client-results{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:14px}
  .client-card,.dog-card{border:1px solid var(--line);background:#fff;border-radius:18px;padding:15px;text-align:left;transition:.18s}
  .client-card:hover,.dog-card:hover{transform:translateY(-2px);box-shadow:0 10px 20px rgba(100,75,75,.08)}
  .client-card b,.dog-card b{display:block;font-size:16px}
  .client-card small,.dog-card small{color:var(--muted)}
  .dog-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .pos-stage{display:none}
  .pos-stage.active{display:block}
  .selection-summary{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
  .selection-chip{background:#fff;border:1px solid var(--line);border-radius:14px;padding:9px 12px;font-size:13px}
  .backline{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
  @media (max-width:900px){
    .client-results{grid-template-columns:1fr}
    .dog-grid{grid-template-columns:1fr 1fr}
  }

[hidden]{display:none!important}.error{color:#a32a3d}.notice{margin-bottom:14px}.table-scroll{overflow:auto}dialog{border:1px solid var(--line);border-radius:22px;padding:24px;width:min(760px,94vw);max-height:90vh;overflow:auto;box-shadow:var(--shadow)}dialog::backdrop{background:#30202b70}button:disabled{opacity:.5;cursor:wait}.payment-check{display:block;margin-top:14px}.kpis{margin-top:18px}@media print{@page{size:80mm auto;margin:0}#receipt{box-sizing:border-box;width:80mm;overflow-wrap:anywhere}#receipt p{font-size:11px}#receipt h3{font-size:13px}}
.calendar-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:18px 0}.calendar-board{display:grid;grid-template-columns:repeat(7,minmax(145px,1fr));gap:10px;overflow-x:auto}.calendar-day{background:var(--rose2,#fff5f5);border-radius:14px;padding:12px;min-height:160px}.calendar-day h4{margin:0 0 12px}.calendar-event{background:white;padding:10px;border-radius:10px;margin:8px 0;border:1px solid #ecd8dc;overflow-wrap:anywhere}.calendar-event p{white-space:pre-wrap}.calendar-board.single{grid-template-columns:1fr}@media(max-width:900px){.topbar{height:auto;min-height:72px;flex-wrap:wrap;gap:8px;padding:12px}.top-actions{flex-wrap:wrap}}
/* PZ_MOBILE_V1: screen-only rules preserve the 80 mm print layout. */
@media screen and (max-width:780px){
  .app,.main,.content,.view,.card,.grid>*{min-width:0;max-width:100%}
  .sidebar{padding:12px;border-bottom:1px solid var(--line)}
  .brand{display:grid;grid-template-columns:48px 1fr;align-items:center;gap:0 12px;text-align:left;margin-bottom:12px}
  .brand .logo-mark{width:48px;height:48px;margin:0;grid-row:1/3}
  .brand h1{font-size:19px;margin:0}.brand small{font-size:10px}
  .sidebar .nav{display:flex;flex-direction:row;gap:8px;overflow-x:auto;padding:4px 0 8px;max-width:100%;overscroll-behavior-x:contain}
  .sidebar .nav button{flex:0 0 auto;display:flex;align-items:center;gap:6px;font-size:13px;min-height:44px;padding:10px 12px;white-space:nowrap}
  .sidebar .nav .ico{font-size:18px}
  .content{padding:12px}.content .view{padding:0!important}
  .topbar{padding:12px;align-items:flex-start}.topbar h2{font-size:20px}
  .top-actions{width:100%;gap:6px;align-items:center}.top-actions .pill{white-space:normal;overflow-wrap:anywhere;font-size:12px}
  .grid{gap:12px}.grid-2,.grid-3{grid-template-columns:minmax(0,1fr)}
  .card{padding:14px;border-radius:18px}.section-title,.backline{gap:10px;flex-wrap:wrap}
  .section-title h3{overflow-wrap:anywhere}.section-title>input{flex:1 1 180px}
  .btn,.pay,.addon,.service,.step-pill,summary{min-height:44px;touch-action:manipulation}
  .btn{white-space:normal;overflow-wrap:anywhere}.input,.select,.textarea{font-size:16px;min-height:44px;min-width:0;max-width:100%}
  input[type=date],input[type=datetime-local]{box-sizing:border-box;min-width:0;width:100%;max-width:100%}
  .field,.formgrid>*{min-width:0}.field label{font-size:13px}
  .paygrid{grid-template-columns:repeat(2,minmax(0,1fr))}.finish{grid-template-columns:minmax(0,1fr)}
  .payment-check{display:flex;gap:10px;align-items:center;min-height:48px}.payment-check input{width:22px;height:22px;flex-shrink:0}
  .pos-stepper{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}.step-pill{justify-content:center;padding:10px 6px;font-size:12px}
  .selection-chip{max-width:100%;overflow-wrap:anywhere}.notice{overflow-wrap:anywhere;line-height:1.5}
  .calendar-board{grid-template-columns:minmax(0,1fr);overflow:visible}.calendar-day{min-height:0}.calendar-event .btn{margin:3px 3px 3px 0}
  .calendar-toolbar{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:end}.calendar-toolbar>*{min-width:0}
  .table-scroll{max-width:100%;overflow-x:auto;overscroll-behavior-x:contain}.table-scroll .table{min-width:580px}
  .cart{table-layout:fixed}.cart th,.cart td{overflow-wrap:anywhere}.cart input{max-width:100%;min-width:0}
  .kpi{padding:12px}.kpi strong{font-size:22px;overflow-wrap:anywhere}
  dialog{box-sizing:border-box;width:calc(100vw - 16px);max-width:calc(100vw - 16px);max-height:calc(100dvh - 24px);padding:18px 14px;border-radius:18px;overscroll-behavior:contain}
  dialog .section-title{margin-top:18px;margin-bottom:0}dialog .section-title .btn{flex:1}
  :focus-visible{outline:2px solid #913b5b;outline-offset:3px}
}
@media screen and (max-width:600px){
  .formgrid,.dog-grid,.client-results{grid-template-columns:minmax(0,1fr)}
  .formgrid>*{grid-column:1/-1!important}
  .services,.addons{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
  .service{padding:12px;min-height:110px}.service b,.addon{overflow-wrap:anywhere}
  .service:hover,.client-card:hover,.dog-card:hover{transform:none}
  .pz-dashboard .pz-kpis,.pz-dashboard .pz-charts{grid-template-columns:minmax(0,1fr)}
}
@media screen and (max-width:360px){.services,.addons{grid-template-columns:minmax(0,1fr)}.brand small{display:none}}
@media(prefers-reduced-motion:reduce){.service,.client-card,.dog-card{transition:none}}



/* Status labels supplement color for accessibility. */
.calendar-day{background:#fffaf7;border:1px solid #eadfda}
.calendar-day-empty{background:#f7f3ed;border:1px dashed #d9cec1}
.calendar-empty{padding:14px 10px;border-radius:12px;background:#eee8df;color:#655b50;text-align:center;font-size:13px}
.calendar-event{border-left-width:5px}
.calendar-event.status-planned{background:#fbe8ef;border-color:#dca3b7;color:#633647}
.calendar-event.status-completed{background:#e5f4ed;border-color:#80b89b;color:#285740}
.calendar-event.status-cancelled{background:#efedf0;border-color:#b7aeb9;color:#655d68}
.visit-status{display:inline-block;padding:5px 9px;margin:6px 0 2px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.4}
.visit-status.status-planned{background:#f2cad9;color:#653148}
.visit-status.status-completed{background:#c7e7d5;color:#244e38}
.visit-status.status-cancelled,.visit-status.status-unknown{background:#e2dce5;color:#534b58}
.calendar-legend{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 16px;font-size:12px;align-items:center}
.calendar-legend .calendar-empty{padding:5px 9px;margin:6px 0 2px}
</style>
<link rel="stylesheet" href="css/admin-portal.css?v=4" media="screen">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="logo-mark"><img src="img/logo-transparent.png?v=2" alt="Logo Puchaty Zakątek"></div>
      <h1>Puchaty Zakątek</h1>
      <small>SPA DLA PSÓW • STRZYŻENIE & MYCIE</small>
    </div>
    <div class="nav">
      <button data-view="calendar"><span class="ico">📅</span>Terminarz</button>
      <button class="active" data-view="pos"><span class="ico">🛒</span>POS</button>
      <button data-view="documents"><span class="ico">📄</span>Dokumenty</button><button data-view="catalog"><span class="ico">📦</span>Magazyn</button><button data-view="business"><span class="ico">📊</span>NDG i raporty</button><button data-view="clients"><span class="ico">👤</span>Klienci</button>
      <button data-view="dogs"><span class="ico">🐶</span>Psy</button>
      <button data-view="costs"><span class="ico">💸</span>Koszty</button>
      <button data-view="accounting"><span class="ico">🧾</span>Rozliczenia wizyt</button>
      <button data-view="reports"><span class="ico">📊</span>Raporty wizyt</button>
      <button data-view="settings"><span class="ico">⚙️</span>Ustawienia</button>
    </div>
    <div class="sidebar-foot sidebar-paws" aria-hidden="true"><svg viewBox="0 0 210 80" fill="none" focusable="false"><path d="M20 59Q100 9 188 42" stroke="#dce4d5" stroke-width="1.5" stroke-dasharray="2 7" stroke-linecap="round"/><g transform="translate(45 49) rotate(-23) scale(.7)" fill="#a7c6b3"><ellipse cx="-15" cy="-8" rx="5" ry="7" transform="rotate(-25 -15 -8)"/><ellipse cx="-6" cy="-17" rx="5" ry="7" transform="rotate(-10 -6 -17)"/><ellipse cx="6" cy="-17" rx="5" ry="7" transform="rotate(10 6 -17)"/><ellipse cx="15" cy="-8" rx="5" ry="7" transform="rotate(25 15 -8)"/><path d="M-14 10C-14 3-7-5 0-5S14 3 14 10C14 19 6 15 0 15S-14 19-14 10Z"/></g><g transform="translate(104 32) rotate(14) scale(.8)" fill="#d9a9b8"><ellipse cx="-15" cy="-8" rx="5" ry="7" transform="rotate(-25 -15 -8)"/><ellipse cx="-6" cy="-17" rx="5" ry="7" transform="rotate(-10 -6 -17)"/><ellipse cx="6" cy="-17" rx="5" ry="7" transform="rotate(10 6 -17)"/><ellipse cx="15" cy="-8" rx="5" ry="7" transform="rotate(25 15 -8)"/><path d="M-14 10C-14 3-7-5 0-5S14 3 14 10C14 19 6 15 0 15S-14 19-14 10Z"/></g><g transform="translate(165 47) rotate(-12) scale(.7)" fill="#a7c6b3"><ellipse cx="-15" cy="-8" rx="5" ry="7" transform="rotate(-25 -15 -8)"/><ellipse cx="-6" cy="-17" rx="5" ry="7" transform="rotate(-10 -6 -17)"/><ellipse cx="6" cy="-17" rx="5" ry="7" transform="rotate(10 6 -17)"/><ellipse cx="15" cy="-8" rx="5" ry="7" transform="rotate(25 15 -8)"/><path d="M-14 10C-14 3-7-5 0-5S14 3 14 10C14 19 6 15 0 15S-14 19-14 10Z"/></g><path d="M105 69l-4-4c-4-4 1-8 4-4 3-4 8 0 4 4Z" fill="#d9a9b8"/></svg></div>
  </aside>

  <main class="main">
    <header class="topbar">
      <h2 id="pageTitle">POS</h2>
      <div class="top-actions">
        <div class="pill">Morzęcin Wielki</div>
        <div class="pill" id="clock">--:--</div>
        <div class="pill" aria-label="Zalogowany użytkownik">👤 Zalogowana osoba: <strong><?= htmlspecialchars($pzName, ENT_QUOTES, 'UTF-8') ?></strong><br><small><?= htmlspecialchars($user->login, ENT_QUOTES, 'UTF-8') ?></small></div>
        <form id="switchUserForm" action="logout.php" method="post"><input type="hidden" name="token" id="logoutToken" value="<?= htmlspecialchars($pzBoot['token'], ENT_QUOTES, 'UTF-8') ?>"><button class="btn ghost" type="submit">Zmień użytkownika</button></form>
      </div>
    </header>

    <div class="content"><div id="pzMessage" class="notice" role="status" aria-live="polite">Wczytywanie danych…</div><button id="retrySale" class="btn primary" onclick="checkPendingSale()" hidden>Sprawdź wynik zapisu</button><button id="retryConfirmedSale" class="btn primary" onclick="finishSale()" hidden>Ponów zapis tej samej wizyty</button><button id="discardPendingSale" class="btn ghost" onclick="discardPendingSale()" hidden>Zamknij niezapisany koszyk</button><button class="btn ghost" data-action="refresh">Odśwież dane</button><div id="setupNotice" class="card" hidden><h3>Przygotowanie bazy</h3><p>Administrator musi dodać tabele modułu i początkowy cennik. Istniejące dane pozostają zachowane.</p><button id="installDb" class="btn primary" data-action="install">Przygotuj bazę modułu</button></div><button class="btn ghost" data-action="last-receipt" id="lastReceiptButton" hidden>🖨️ Potwierdzenie 80 mm</button><button id="lastDocumentButton" type="button" class="btn ghost" data-commerce="visitpreview" hidden>📄 Dokument ostatniej wizyty</button><div id="appPanels" hidden>
      
      <section class="view active" id="pos">
        <div class="pos-stepper">
          <div class="step-pill active" id="stepPill1">1. Klient</div>
          <div class="step-pill" id="stepPill2">2. Pies</div>
          <div class="step-pill" id="stepPill3">3. Usługi</div>
          <div class="step-pill" id="stepPill4">4. Płatność</div>
        </div>

        <div class="pos-stage active" id="stageClient">
          <div class="card client-search-wrap">
            <div class="section-title">
              <h3>👤 Wybierz klienta</h3>
              <button class="btn primary" data-action="client" data-write>+ Nowy klient</button>
            </div>
            <input id="posClientSearch" class="input" placeholder="Wpisz nazwisko, telefon lub e-mail..." />
            <div class="client-results" id="posClientResults"></div>
          </div>
        </div>

        <div class="pos-stage" id="stageDog">
          <div class="card">
            <div class="backline">
              <button class="btn ghost" onclick="goToStage('client')">← Zmień klienta</button>
              <div class="selection-chip" id="clientSummaryChip"></div>
            </div>
            <div class="section-title">
              <h3>🐶 Wybierz psa</h3>
              <button class="btn primary" data-action="dog" data-write>+ Dodaj psa</button>
            </div>
            <div class="dog-grid" id="posDogResults"></div>
          </div>
        </div>

        <div class="pos-stage" id="stageService">
          <div class="selection-summary">
            <div class="selection-chip" id="selectedClientChip"></div>
            <div class="selection-chip" id="selectedDogChip"></div>
            <button class="btn ghost" onclick="goToStage('dog')">Zmień psa</button>
          </div>

          <div class="grid grid-2">
            <div>
              <div class="card">
                <div class="section-title">
                  <h3>🐾 Usługi według gabarytu</h3>
                  <input id="serviceSearch" class="input" style="max-width:260px" placeholder="Szukaj usługi..." />
                </div>
                <div id="dogSizeHint" class="notice" style="margin-bottom:12px"></div>
                <div class="services" id="services"></div>
              </div>
              <div class="card" style="margin-top:18px">
                <h3>✂️ Dodatki</h3>
                <div class="addons" id="addons"></div>
              </div>
            </div>

            <div class="checkout">
              <div class="card">
                <div class="section-title"><h3>Podsumowanie wizyty</h3><button class="btn ghost" onclick="clearCart()">Wyczyść</button></div>
                <div id="lastSelectedBox" style="display:none;margin:10px 0 14px;padding:14px;border-radius:16px;background:linear-gradient(135deg,var(--mint2),var(--rose2));border:2px solid #efb5c2;box-shadow:0 8px 18px rgba(126,92,92,.08)">
                  <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-weight:800">Ostatnio wybrana usługa</div>
                  <div id="lastSelectedName" style="font-weight:900;font-size:17px;margin-top:5px"></div>
                  <div id="lastSelectedPrice" style="font-weight:900;font-size:24px;color:#8d4050;margin-top:3px"></div>
                </div>

                <table class="cart">
                  <thead><tr><th>Usługa</th><th>Cena</th></tr></thead>
                  <tbody id="cartBody"></tbody>
                </table>
                <div class="sum"><span>Suma</span><span id="cartTotal">0 zł</span></div>

                <button class="btn primary" style="width:100%" onclick="goToStage('payment')">Przejdź do płatności →</button>
              </div>
            </div>
          </div>
        </div>

        <div class="pos-stage" id="stagePayment">
          <div class="grid grid-2">
            <div class="card">
              <div class="backline">
                <button class="btn ghost" onclick="goToStage('service')">← Wróć do usług</button>
              </div>
              <h3>💳 Płatność</h3>
              <div class="selection-summary">
                <div class="selection-chip" id="paymentClientChip"></div>
                <div class="selection-chip" id="paymentDogChip"></div>
              </div>
              <div class="paygrid">
                <button class="pay active" data-pay="Gotówka">💵 Gotówka</button>
                <button class="pay" data-pay="Karta">💳 Karta</button>
                <button class="pay" data-pay="BLIK">📱 BLIK</button>
                <button class="pay" data-pay="Przelew">🏦 Przelew</button>
              </div>
              <label class="payment-check"><input type="checkbox" id="paymentReceived" checked> Potwierdzam otrzymanie pełnej wpłaty</label><label class="payment-check"><input type="checkbox" id="issueInvoice"> Wystaw fakturę (opcjonalnie — wymaga danych i adresu nabywcy)</label><div class="finish">
                
                <button id="finishSale" data-write class="btn primary" onclick="finishSale()">✓ Zakończ sprzedaż</button>
              </div>
            </div>

            <div class="card">
              <h3>Podsumowanie płatności</h3>
              <table class="cart">
                <thead><tr><th>Usługa</th><th>Cena</th></tr></thead>
                <tbody id="paymentCartBody"></tbody>
              </table>
              <div class="sum"><span>Do zapłaty</span><span id="paymentCartTotal">0 zł</span></div>
            </div>
          </div>
        </div>
      </section>

      
<section class="view" id="clients"><div class="card"><div class="section-title"><h3>Klienci</h3><button class="btn primary" data-action="client" data-write>+ Dodaj klienta</button></div><input id="clientListSearch" class="input" placeholder="Szukaj po nazwie, telefonie lub e-mailu"><div id="clientList"></div></div></section>
<section class="view" id="dogs"><div class="card"><div class="section-title"><h3>Kartoteka psów</h3><button class="btn primary" data-action="dog" data-write>+ Dodaj psa</button></div><div id="dogList"></div></div></section>
<section class="view" id="calendar"><div class="card salon-calendar"><div class="section-title calendar-title"><div><p class="salon-eyebrow">Z TROSKĄ O KAŻDEGO PUPILA</p><h3>Terminarz salonu<span class="salon-title-dot">.</span></h3><p class="muted calendar-subtitle">Wizyty, klienci i spokojnie zaplanowany dzień.</p></div><button class="btn primary" data-action="plan" data-write>+ Nowa wizyta</button><button class="btn ghost" data-action="block" data-write>Zablokuj termin</button></div><div class="calendar-toolbar"><label>Widok <select id="calendarMode" class="select"><option value="week">Tydzień</option><option value="day">Dzień</option><option value="list">Lista</option></select></label><button class="btn ghost" data-calendar-shift="-1" aria-label="Poprzedni okres">← Poprzedni</button><label>Przejdź do dnia <input type="date" class="input" id="calendarDate"></label><button class="btn ghost" data-calendar-shift="1" aria-label="Następny okres">Następny →</button><button class="btn ghost" data-action="calendar-today">Dzisiaj</button></div><div class="calendar-meta"><p id="calendarRange"></p><div class="calendar-legend" aria-label="Kolory statusów"><span class="visit-status status-planned">Zarezerwowana</span><span class="visit-status status-completed">Rozliczona</span><span class="calendar-empty">Brak wizyt</span></div></div><div id="calendarMobileDays" class="salon-mobile-days" aria-label="Wybierz dzień"></div><div id="calendarBoard"></div><details class="calendar-history"><summary>Historia i lista wizyt</summary><div class="formgrid"><div class="field"><label for="visitFrom">Od</label><input type="date" id="visitFrom" class="input"></div><div class="field"><label for="visitTo">Do</label><input type="date" id="visitTo" class="input"></div></div><div id="visitList"></div></details></div></section>
<section class="view" id="costs"><div class="card"><div class="section-title"><h3>Ewidencja kosztów</h3><button class="btn primary" data-action="expense" data-write>+ Dodaj koszt</button></div><div id="expenseList"></div></div></section>
<section class="view" id="accounting"><div class="card"><h3>Okres zestawienia</h3><div class="formgrid"><div class="field"><label for="reportFrom">Od</label><input class="input" type="date" id="reportFrom"></div><div class="field"><label for="reportTo">Do</label><input class="input" type="date" id="reportTo"></div></div><p class="muted">Zestawienie zapisanych wizyt, wpłat i kosztów. Eksport obejmuje wybrany okres.</p></div><div id="accountingTotals" class="kpis"></div><div class="card"><p id="limitInfo"></p><button class="btn soft" data-action="export-sales">Eksport wizyt CSV</button> <button class="btn mint" data-action="export-expenses">Eksport kosztów CSV</button><p class="muted">To zestawienie pomocnicze, bez generowania deklaracji PIT.</p></div></section>
<section class="view" id="reports"><div class="card"><h3>Raport wizyt</h3><p id="reportSummary"></p><div class="formgrid"><div class="field"><label for="visitReportFrom">Od</label><input class="input" type="date" id="visitReportFrom"></div><div class="field"><label for="visitReportTo">Do</label><input class="input" type="date" id="visitReportTo"></div></div><p><button class="btn soft" data-visit-period="month">Ten miesiąc</button> <button class="btn soft" data-visit-period="year">Ten rok</button> <button class="btn ghost" data-action="export-sales">Eksport wizyt CSV</button> <button class="btn ghost" id="printVisitReport">Drukuj raport</button></p><div id="visitReportCharts"></div><h4>Podsumowanie miesięczne</h4><div id="reportTable"></div></div></section>
<section class="view" id="settings"><div class="card"><h3>Ustawienia salonu i cennik</h3><p><button type="button" class="btn ghost" data-commerce="mailowner">E-mail właściciela salonu</button></p><form id="settingsForm"><div id="settingsFields" class="formgrid"></div><p><button class="btn primary" type="submit">Zapisz ustawienia</button></p></form><p class="muted">Ustawienia może zmieniać administrator. Druk odbywa się przez okno drukowania przeglądarki; wybierz papier 80 mm. Kopie bazy i integracja z drukarką fiskalną wymagają osobnej konfiguracji.</p></div></section>
</div>
    </div>
  <section class="view" id="documents" style="padding:24px"><div class="card" id="documentsPanel"></div></section><section class="view" id="catalog" style="padding:24px"><div class="card" id="catalogPanel"></div></section><section class="view" id="business" style="padding:24px"><div class="card" id="businessPanel"></div></section>
</main>
</div>

<dialog id="pzModal" aria-labelledby="modalTitle"><form id="modalForm"><h3 id="modalTitle"></h3><div id="modalFields" class="formgrid"></div><p id="modalError" class="error" role="alert"></p><div class="section-title"><button type="button" class="btn ghost" data-action="close-modal">Zamknij</button><button type="submit" id="modalSave" class="btn primary">Zapisz</button></div></form></dialog>
<div id="receipt" class="receiptonly">
  <h2>Puchaty Zakątek</h2>
  <div style="text-align:center;font-size:11px;margin-bottom:8px">SPA dla psów • Morzęcin Wielki</div>
  <table id="receiptTable"></table>
  <div style="margin-top:10px;font-weight:bold;text-align:right" id="receiptTotal"></div>
  <div style="margin-top:16px;text-align:center;font-size:11px">Dziękujemy 🐾</div>
</div>

<script type="application/json" id="pzBoot"><?php echo json_encode($pzBoot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?></script>
<script src="js/app.js?v=0.6.12-table-actions"></script><script src="js/reports.js?v=0.5.5"></script><script src="js/visit-reports.js?v=0.5.5"></script><script src="js/commerce.js?v=0.6.12-table-actions"></script>
</body>
</html>
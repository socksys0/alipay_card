<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
require_once __DIR__ . "/../conn/db_conn.php";
require_once __DIR__ . "/../func.php";

$admin_config = get_admin_config();

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>登录 · 个人收款卡密管理系统</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,600;9..144,700&family=Manrope:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<style>
  :root{
    --teal-900:#063B3A;
    --teal-700:#0E7C7B;
    --teal-500:#0EA5A4;
    --teal-300:#3DD6C4;
    --mist:#F4FBFB;
    --ink:#0A3D3D;
    --line:rgba(14,124,123,.18);
    --glass:rgba(255,255,255,.10);
    --glass-border:rgba(255,255,255,.28);
    --danger:#E0584E;
    --shadow-soft:0 24px 60px -20px rgba(6,59,58,.45);
    --shadow-btn:0 10px 24px -8px rgba(14,165,164,.6);
  }

  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%}
  body{
    font-family:'Manrope',system-ui,-apple-system,sans-serif;
    color:var(--ink);
    min-height:100vh;
    display:grid;
    place-items:center;
    padding:24px;
    overflow:hidden;
    position:relative;
    background:
      radial-gradient(120% 120% at 15% 10%, #0a4a48 0%, transparent 55%),
      radial-gradient(120% 120% at 85% 90%, #0c5b59 0%, transparent 55%),
      linear-gradient(135deg,#063B3A 0%,#0a4f4d 50%,#0E7C7B 100%);
  }

  .bg-orb{
    position:fixed;border-radius:50%;filter:blur(70px);opacity:.55;
    pointer-events:none;z-index:0;
    animation:float 14s ease-in-out infinite;
  }
  .bg-orb.a{width:480px;height:480px;left:-120px;top:-120px;
    background:radial-gradient(circle,#3DD6C4 0%,transparent 70%)}
  .bg-orb.b{width:520px;height:520px;right:-160px;bottom:-180px;
    background:radial-gradient(circle,#0EA5A4 0%,transparent 70%);
    animation-delay:-7s}
  .bg-orb.c{width:300px;height:300px;left:55%;top:60%;
    background:radial-gradient(circle,#5FE3D2 0%,transparent 70%);
    animation-delay:-3s;opacity:.35}

  .bg-grid{
    position:fixed;inset:0;z-index:0;pointer-events:none;
    background-image:
      linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),
      linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);
    background-size:54px 54px;
    mask-image:radial-gradient(ellipse 80% 60% at 50% 40%,#000 30%,transparent 80%);
    -webkit-mask-image:radial-gradient(ellipse 80% 60% at 50% 40%,#000 30%,transparent 80%);
  }
  .bg-noise{
    position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.06;mix-blend-mode:overlay;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  }

  @keyframes float{
    0%,100%{transform:translate(0,0) scale(1)}
    50%{transform:translate(20px,-30px) scale(1.06)}
  }

  .card{
    position:relative;z-index:2;
    width:100%;max-width:430px;
    padding:48px 44px 40px;
    border-radius:28px;
    background:linear-gradient(160deg,rgba(255,255,255,.16),rgba(255,255,255,.06));
    backdrop-filter:blur(24px) saturate(140%);
    -webkit-backdrop-filter:blur(24px) saturate(140%);
    border:1px solid var(--glass-border);
    box-shadow:var(--shadow-soft),inset 0 1px 0 rgba(255,255,255,.25);
    color:var(--mist);
    animation:rise .9s cubic-bezier(.2,.7,.2,1) both;
  }
  @keyframes rise{
    from{opacity:0;transform:translateY(24px) scale(.98)}
    to{opacity:1;transform:translateY(0) scale(1)}
  }

  .brand{
    display:flex;align-items:center;gap:12px;margin-bottom:6px;
    animation:fade .6s .15s both;
  }
  .brand-mark{
    width:38px;height:38px;border-radius:12px;
    background:linear-gradient(135deg,var(--teal-300),var(--teal-500));
    display:grid;place-items:center;
    box-shadow:0 6px 18px -6px rgba(61,214,196,.7);
  }
  .brand-mark svg{width:20px;height:20px}
  .brand-name{font-size:14px;letter-spacing:.18em;text-transform:uppercase;opacity:.85;font-weight:500}

  .title{
    font-family:'Fraunces',serif;
    font-weight:600;
    font-size:38px;
    line-height:1.1;
    letter-spacing:-.01em;
    margin-top:18px;
    color:#fff;
    animation:fade .6s .25s both;
  }
  .title em{font-style:italic;font-weight:300;color:var(--teal-300)}
  .subtitle{
    margin-top:10px;font-size:14px;line-height:1.6;opacity:.78;
    animation:fade .6s .35s both;
  }
  @keyframes fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

  .form{margin-top:34px;display:flex;flex-direction:column;gap:20px}
  .field{position:relative;animation:fade .6s both}
  .field:nth-of-type(1){animation-delay:.45s}
  .field:nth-of-type(2){animation-delay:.55s}

  .field label{
    display:block;font-size:12px;font-weight:600;letter-spacing:.1em;
    text-transform:uppercase;opacity:.7;margin-bottom:8px;
  }
  .input-wrap{
    position:relative;display:flex;align-items:center;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.16);
    border-radius:14px;
    transition:border-color .25s,box-shadow .25s,background .25s;
  }
  .input-wrap:focus-within{
    border-color:rgba(61,214,196,.7);
    box-shadow:0 0 0 4px rgba(61,214,196,.16);
    background:rgba(255,255,255,.09);
  }
  .input-wrap .ico{
    position:absolute;left:16px;width:18px;height:18px;opacity:.6;pointer-events:none;
    transition:opacity .25s;
  }
  .input-wrap:focus-within .ico{opacity:.95}
  .input-wrap input{
    width:100%;
    padding:15px 16px 15px 46px;
    background:transparent;border:0;outline:none;
    color:#fff;font-size:15px;font-family:inherit;
    letter-spacing:.01em;
  }
  .input-wrap input::placeholder{color:rgba(244,251,251,.45)}
  .toggle-pw{
    position:absolute;right:12px;
    background:transparent;border:0;cursor:pointer;
    width:34px;height:34px;border-radius:8px;display:grid;place-items:center;
    color:rgba(244,251,251,.6);
    transition:background .2s,color .2s;
  }
  .toggle-pw:hover{background:rgba(255,255,255,.1);color:#fff}
  .toggle-pw svg{width:18px;height:18px}

  .row{
    display:flex;align-items:center;justify-content:space-between;gap:16px;
    margin-top:-4px;
    animation:fade .6s .65s both;
  }
  .check{
    display:flex;align-items:center;gap:10px;cursor:pointer;
    font-size:13px;opacity:.85;user-select:none;
  }
  .check input{position:absolute;opacity:0;width:0;height:0}
  .check .box{
    width:18px;height:18px;border-radius:6px;
    border:1.5px solid rgba(255,255,255,.4);
    display:grid;place-items:center;
    transition:background .2s,border-color .2s;
  }
  .check .box svg{width:12px;height:12px;opacity:0;transform:scale(.6);transition:.2s}
  .check input:checked + .box{
    background:linear-gradient(135deg,var(--teal-300),var(--teal-500));
    border-color:transparent;
  }
  .check input:checked + .box svg{opacity:1;transform:scale(1)}
  .check input:focus-visible + .box{box-shadow:0 0 0 4px rgba(61,214,196,.25)}

  .link{
    color:var(--teal-300);text-decoration:none;font-size:13px;font-weight:500;
    position:relative;transition:color .2s;
  }
  .link::after{
    content:"";position:absolute;left:0;bottom:-2px;height:1px;width:0;
    background:var(--teal-300);transition:width .25s;
  }
  .link:hover{color:#fff}
  .link:hover::after{width:100%}

  .btn{
    position:relative;overflow:hidden;
    margin-top:8px;
    width:100%;padding:16px 24px;
    border:0;border-radius:14px;cursor:pointer;
    font-family:inherit;font-size:15px;font-weight:600;letter-spacing:.02em;
    color:#062B2A;
    background:linear-gradient(135deg,#5FE3D2 0%,#0EA5A4 60%,#0E7C7B 100%);
    box-shadow:var(--shadow-btn);
    transition:transform .2s ease,box-shadow .2s ease,filter .2s;
    animation:fade .6s .75s both;
  }
  .btn:hover{transform:translateY(-2px);box-shadow:0 16px 32px -8px rgba(14,165,164,.7);filter:brightness(1.04)}
  .btn:active{transform:translateY(0) scale(.99)}
  .btn .label{display:inline-flex;align-items:center;gap:10px}
  .btn .label svg{width:18px;height:18px;transition:transform .25s}
  .btn:hover .label svg{transform:translateX(4px)}
  .btn .ripple{position:absolute;border-radius:50%;transform:scale(0);
    background:rgba(255,255,255,.5);animation:ripple .6s ease-out;pointer-events:none}
  @keyframes ripple{to{transform:scale(2.5);opacity:0}}

  .btn.loading .label-text{visibility:hidden}
  .btn.loading::after{
    content:"";position:absolute;left:50%;top:50%;width:20px;height:20px;margin:-10px 0 0 -10px;
    border:2.5px solid rgba(6,43,42,.35);border-top-color:#062B2A;border-radius:50%;
    animation:spin .7s linear infinite;
  }
  @keyframes spin{to{transform:rotate(360deg)}}

  .foot{
    margin-top:26px;text-align:center;font-size:13px;opacity:.75;
    animation:fade .6s .85s both;
  }

  .error-msg{
    margin-top:-6px;font-size:12px;color:#FFD9D5;background:rgba(224,88,78,.16);
    padding:0 10px;border-radius:8px;max-height:0;overflow:hidden;
    transition:max-height .25s,padding .25s;
  }
  .error-msg.show{max-height:40px;padding:8px 10px}

  @media (max-width:480px){
    .card{padding:36px 26px 30px;border-radius:24px}
    .title{font-size:32px}
    .bg-orb.c{display:none}
  }
</style>
</head>
<body>
  <div class="bg-grid"></div>
  <div class="bg-orb a"></div>
  <div class="bg-orb b"></div>
  <div class="bg-orb c"></div>
  <div class="bg-noise"></div>

  <main class="card" role="main" aria-label="登录">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="#062B2A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3c3 4 5 6 5 9a5 5 0 0 1-10 0c0-3 2-5 5-9z"/>
        </svg>
      </span>
      <span class="brand-name">CardKey&nbsp;·&nbsp;卡密管理</span>
    </div>

    <h1 class="title">欢迎回来，<em>请登录</em></h1>
    <p class="subtitle">管理您的卡密系统，开启专属收款之旅。</p>

    <form class="form" id="loginForm" novalidate>
      <div class="field">
        <label for="account">账号</label>
        <div class="input-wrap">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="#F4FBFB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="4"/>
              <path d="M4 20c0-3.5 3.6-6 8-6s8 2.5 8 6"/>
            </svg>
          </span>
          <input id="account" name="account" type="text" autocomplete="username" placeholder="请输入账号" required />
        </div>
      </div>

      <div class="field">
        <label for="password">密码</label>
        <div class="input-wrap">
          <span class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="#F4FBFB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="4" y="10" width="16" height="11" rx="2"/>
              <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            </svg>
          </span>
          <input id="password" name="password" type="password" autocomplete="current-password" placeholder="请输入密码" required />
          <button type="button" class="toggle-pw" id="togglePw" aria-label="显示密码">
            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div class="error-msg" id="errPassword"></div>
      </div>

      <div class="row">
        <label class="check">
          <input type="checkbox" id="remember" />
          <span class="box">
            <svg viewBox="0 0 24 24" fill="none" stroke="#062B2A" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 6 9 17l-5-5"/>
            </svg>
          </span>
          记住我
        </label>
      </div>

      <button type="submit" class="btn" id="submitBtn">
        <span class="label">
          <span class="label-text">登录</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>
          </svg>
        </span>
      </button>
    </form>

    <p class="foot">卡密管理系统 © 2026</p>
  </main>

<script>
  (function(){
    const form = document.getElementById('loginForm');
    const account = document.getElementById('account');
    const password = document.getElementById('password');
    const errEl = document.getElementById('errPassword');
    const togglePw = document.getElementById('togglePw');
    const eyeIcon = document.getElementById('eyeIcon');
    const submitBtn = document.getElementById('submitBtn');

    togglePw.addEventListener('click', function(){
      const isPw = password.type === 'password';
      password.type = isPw ? 'text' : 'password';
      togglePw.setAttribute('aria-label', isPw ? '隐藏密码' : '显示密码');
      eyeIcon.innerHTML = isPw
        ? '<path d="M2 2l20 20"/><path d="M6.7 6.7A10.4 10.4 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 3.3-.6"/><path d="M9.8 4.2A10.4 10.4 0 0 1 12 4c6.5 0 10 8 10 8a17.6 17.6 0 0 1-2.2 3.2"/><path d="M9.5 9.5a3 3 0 0 0 4.2 4.2"/>'
        : '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
    });

    submitBtn.addEventListener('click', function(e){
      const r = document.createElement('span');
      r.className = 'ripple';
      const rect = submitBtn.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      r.style.width = r.style.height = size + 'px';
      r.style.left = (e.clientX - rect.left - size/2) + 'px';
      r.style.top = (e.clientY - rect.top - size/2) + 'px';
      submitBtn.appendChild(r);
      setTimeout(()=>r.remove(), 600);
    });

    function showError(msg){
      errEl.textContent = msg;
      errEl.classList.add('show');
    }
    function clearError(){
      if(errEl.classList.contains('show')){
        errEl.classList.remove('show');
        errEl.textContent = '';
      }
    }
    password.addEventListener('input', clearError);

    form.addEventListener('submit', function(e){
      e.preventDefault();
      clearError();
      const acc = account.value.trim();
      const pw = password.value;

      if(!acc){ account.focus(); showError('请输入账号。'); return; }
      if(!pw){ password.focus(); showError('请输入密码。'); return; }
      //if(pw.length < 6){ password.focus(); showError('密码至少 6 位。'); return; }

      submitBtn.classList.add('loading');
      submitBtn.disabled = true;

      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'admin_op.php');
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function(){
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
        try{
          const res = JSON.parse(xhr.responseText);
          if(res.code === 0){
            window.location.href = 'admin.php';
          } else {
            showError(res.msg || '登录失败，请重试');
          }
        } catch(e){
          showError('服务器响应异常');
        }
      };
      xhr.onerror = function(){
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
        showError('网络请求失败');
      };
      xhr.send('action=login&username=' + encodeURIComponent(acc) + '&password=' + encodeURIComponent(pw));
    });
  })();
</script>
</body>
</html>
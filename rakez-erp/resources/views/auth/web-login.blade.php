<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل الدخول — اختبار الدردشة</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 16px; }
        .card { background: #1e293b; border-radius: 12px; padding: 28px; width: 100%; max-width: 400px; border: 1px solid #334155; }
        h1 { margin: 0 0 8px; font-size: 1.25rem; }
        p { margin: 0 0 20px; font-size: 0.875rem; color: #94a3b8; }
        label { display: block; margin-bottom: 6px; font-size: 0.875rem; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #f8fafc; margin-bottom: 14px; }
        input:focus { outline: none; border-color: #38bdf8; }
        .err { color: #f87171; font-size: 0.875rem; margin-bottom: 12px; }
        button { width: 100%; padding: 12px; border: none; border-radius: 8px; background: #0ea5e9; color: #fff; font-weight: 600; cursor: pointer; }
        button:hover { background: #0284c7; }
        .remember { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>تسجيل الدخول</h1>
        <p>بعد الدخول ستُوجَّه إلى صفحة اختبار الدردشة. الجلسة تُستخدم مع واجهة API عند تفعيل Sanctum للنطاق الحالي.</p>

        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ url('/login') }}">
            @csrf
            <label for="email">البريد</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">

            <label for="password">كلمة المرور</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <label class="remember">
                <input type="checkbox" name="remember" value="1">
                تذكرني
            </label>

            <button type="submit">دخول</button>
        </form>
    </div>
</body>
</html>

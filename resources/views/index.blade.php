<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="{{ asset('css/app.css') }}" rel="stylesheet" type="text/css" />

    <link rel="icon" type="image/x-icon" href="/favicon.ico">

    <!-- Muse UI 推荐的字体和图标库 -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700,400italic">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <!-- 阿里巴巴icon图标样式文件 -->
    <link rel="stylesheet" href="//at.alicdn.com/t/font_500440_9oye91czwt8.css">

    <title>webchatS在线聊天室</title>

    <script type='text/javascript'>
        window.Laravel = <?php echo json_encode(['csrfToken' => csrf_token(),]); ?>
    </script>
</head>
<body>
    <div id="app"></div>
    <script type="text/javascript" src="{{ asset('js/app.js') }}"></script>
</body>
</html>

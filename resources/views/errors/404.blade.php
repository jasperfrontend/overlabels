{{-- resources/views/overlay/404.blade.php --}}
    <!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>404 - Not Found</title>
    <style>
        * {
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background: #2f2e42;
            color: #eae9f6;
        }
        .page-content {
            max-width: 800px;
            margin: 0 auto;
            height: 100vh;
            padding: 1rem;
            display: grid;
            place-content: center;
        }
        h1 {
            font-size: 42px;
            margin-bottom: 20px;
        }
        p {
            font-size: 18px;
        }
        small {
            line-height: 1.4;
        }
        a {
            color: #b599f1;
            text-decoration: underline;
            &:hover {
                text-decoration: none;
                color: #b599f1;
            }
        }
        svg {
            width: 64px;
            height: 64px;
            text-align: center;
            display: block;
            margin: 0 auto;
            fill: #b599f1;
            color: #b599f1;
        }
        @media (max-width: 640px) {
            h1 {
                font-size: 32px;
            }
            p {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
<div class="page-content">
    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 32 32"><path d="M16 31h-2v-.228a3.014 3.014 0 0 0-1.947-2.81l-3.532-1.324A3.903 3.903 0 0 1 6 23h2a1.895 1.895 0 0 0 1.224 1.766l3.531 1.324A5.023 5.023 0 0 1 16 30.772z" fill="currentColor"></path><path d="M30 31h-2v-.228a3.014 3.014 0 0 0-1.947-2.81l-3.532-1.324A3.903 3.903 0 0 1 20 23h2a1.895 1.895 0 0 0 1.224 1.766l3.531 1.324A5.023 5.023 0 0 1 30 30.772z" fill="currentColor"></path><path d="M11 13h6v2h-6z" fill="currentColor"></path><path d="M23.44 8L22.17 3.45A2.009 2.009 0 0 0 20.246 2H7.754a2.009 2.009 0 0 0-1.923 1.45L4.531 8H2v2h2v7a2.002 2.002 0 0 0 2 2v2h2v-2h12v2h2v-2a2.002 2.002 0 0 0 2-2v-7h2V8zM7.755 4h12.492l1.428 5H6.326zM22 13h-2v2h2v2H6v-2h2v-2H6v-2h16z" fill="currentColor"></path></svg>
    <h1>It seems you took a wrong turn</h1>
    <p>Sorry, the page you're looking for doesn't exist.</p>
    <p>
        <small>What happened?<br />You typed in or clicked on a link that resulted in nothing,
        <br />
        so now you see this page. You could try visiting <a href="https://overlabels.com" target="_blank" rel="nofollow,noindex">overlabels.com</a> instead.</small></p>
</div>
</body>
</html>

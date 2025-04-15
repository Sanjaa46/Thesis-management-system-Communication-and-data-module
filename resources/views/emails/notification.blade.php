<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f8fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #e8eaed;
        }
        .header img {
            max-height: 50px;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            padding: 20px 0;
            text-align: center;
            font-size: 12px;
            color: #718096;
            border-top: 1px solid #e8eaed;
        }
        .button {
            display: inline-block;
            background-color: #4F46E5;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 3px;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #3c35a8;
        }
        @media only screen and (max-width: 600px) {
            .container {
                width: 100%;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!-- You can add your logo here -->
            <h2>{{ $systemName }}</h2>
        </div>
        
        <div class="content">
            <h1>{{ $title }}</h1>
            
            <div>
                {!! nl2br(e($content)) !!}
            </div>
            
            @if(isset($url) && $url)
                <div style="text-align: center; margin-top: 30px;">
                    <a href="{{ $url }}" class="button">Дэлгэрэнгүй харах</a>
                </div>
            @endif
        </div>
        
        <div class="footer">
            <p>Энэхүү и-мэйл нь {{ $systemName }}-ээс автоматаар илгээгдсэн болно.</p>
            <p>&copy; {{ date('Y') }} МУИС. Бүх эрх хуулиар хамгаалагдсан.</p>
        </div>
    </div>
</body>
</html>
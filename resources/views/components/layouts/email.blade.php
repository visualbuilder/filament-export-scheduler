<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{config('app.name')}} Email</title>
</head>
<body style="font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            color: #333;
            line-height: 1.5;
            ">
<div class="email-container" style="max-width: 600px;margin: 0 auto;background: #ffffff; padding: 20px;box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
    {{ $slot }}
</div>
</body>
</html>

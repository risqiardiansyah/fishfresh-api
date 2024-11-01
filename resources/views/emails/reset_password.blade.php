<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
            color: #007bff;
        }

        p {
            margin: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: #444;
        }

        a.reset-btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .reset-btn:hover {
            background-color: #0056b3;
        }

        .footer {
            margin-top: 30px;
            color: #888;
        }

        /* Penyesuaian untuk tampilan mobile */
        @media only screen and (max-width: 600px) {
            h1 {
                font-size: 16px;
                font-weight: bold;
                margin-bottom: 30px;
                color: #007bff;
            }
            p {
                font-size: 14px;
            }
            .reset-btn {
                padding: 10px 20px;
            }
        }

        /* Penyesuaian untuk tampilan tablet */
        @media only screen and (min-width: 601px) and (max-width: 1024px) {
            h1 {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 30px;
                color: #007bff;
            }
            p {
                font-size: 15px;
            }
            .reset-btn {
                padding: 11px 22px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>{{ $data['title'] }}</h1>

        <p>Dear {{ $data['user'] }},</p>
        <p>Anda telah meminta untuk mengatur ulang kata sandi.</p>
        <p>Silakan klik tautan berikut untuk mengatur ulang kata sandi Anda:</p>
        <a href="{{ $data['url'] }}" class="reset-btn"><span class="reset-button-txt">Reset Password</span></a>

        <p style="margin-top: 20px;">If you did not request this, please ignore this email.</p>

        <div class="footer">
            <p>Regards,</p>
            <p>Vogaon</p>
        </div>
    </div>
</body>

</html>

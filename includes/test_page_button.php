<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .test {
            display: inline-block;
            position: fixed;
            top: 2rem;
            left: 2rem;
            padding: 10px 20px;
            background-color: #28a745;
            color: #fff;
            text-decoration: none;
            border-radius: 25px;
            box-shadow: 0 0 8px rgba(40, 167, 69, 0.5);
            transition: all 0.3s ease;
        }

        .test:hover {
            background: linear-gradient(135deg, #28a745, #28a745);

            box-shadow: 
                0 0 12px rgba(40, 167, 69, 0.7),
                0 0 20px rgba(40, 167, 69, 0.5);

            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <a href="../test/test.php" class="test">Go to Test Page</a>
</body>
</html>
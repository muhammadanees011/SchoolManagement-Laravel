<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
</head>
<body>
    <h1>Welcome Mail from StudentPay</h1>
    <h4>{{ $mailData['title'] }}</h4> 
    <p>Your password is: {{ $mailData['body'] }}</p>
         
    <p>Thank you</p>
</body>
</html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentPay</title>
    <style>
        body {
            color:#777;
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4; /* Background color for the entire body */
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff; /* Background color for the content */
            border: 1px solid #ddd; /* Border around the content */
            border-radius: 8px; /* Optional: Add rounded corners */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Optional: Add a subtle shadow */
        }
        h4 {
            text-align: center;
        }
        p {
            margin-bottom: 15px;
            text-align: justify;
        }
        strong {
            font-weight: bold;
        }
        footer {
            margin-top: 20px;
            font-size: 0.9em; /* Adjust the font size as needed */
            color: #777;
            text-align: center;
            font-style: italic; /* Add italic style */
        }
        footer p {
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <img src="{{$message->embed(public_path().'/images/background.jpg')}}" alt="Icon" class="icon-img" style="display: block; margin: 0 auto 20px; width: 600px !important; max-height:200px !important;">
        <h4 style="color:#235667;">Your Daily Update From StudentPay</h4>
        <p>
            we have received a total of <strong style="color:#235667 !important;">{{$today_students}}</strong> students today, bringing our total number of students to  <strong style="color:#235667 !important;">{{$total_students}}</strong> .
            <br>
            And we have received a total of <strong style="color:#235667 !important;">{{$today_staff}}</strong> staff today, bringing our total number of staff to  <strong style="color:#235667 !important;">{{$total_staff}}</strong> .
            <br>
        </p>

        @if (!empty($new_schools))
            <p><strong>Users associated with the following schools have been dropped as these schools are not found on the StudentPay portal:</strong></p>
            <ul>
                @foreach ($new_schools as $new_schools)
                    <li>{{ $new_schools }}</li>
                @endforeach
            </ul>
        @endif

        <footer>
            <p>
                This is an auto-generated email, Please do not respond to this email.
                <br>
                <br>
                Best Regards,<br>
                StudentPay Team
            </p>
        </footer>
    </div>
</body>

</html>

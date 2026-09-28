<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <style>

        body {
            font-family: DejaVu Sans, sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .title {
            font-size: 22px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            border: 1px solid #333;
            padding: 8px;
        }

        th {
            background: #eeeeee;
        }

        .section {
            margin-top: 20px;
        }

    </style>

</head>

<body>

<div class="header">

    <div class="title">
        الوصفة الطبية
    </div>

</div>


<table>

    <tr>

        <td>
            <strong>رقم الوصفة:</strong>
            {{ $prescription->id }}
        </td>

        <td>
            <strong>تاريخ الزيارة:</strong>
            {{ optional($prescription->visit?->visit_date)->format('Y-m-d H:i') }}
        </td>

    </tr>

    <tr>

        <td>

            <strong>الطبيب:</strong>

            {{ $prescription->visit?->doctor?->user?->name ?? 'غير محدد' }}

        </td>

        <td>

            <strong>التخصص:</strong>

            {{ $prescription->visit?->doctor?->specialization?->name ?? 'غير محدد' }}

        </td>

    </tr>

    <tr>

        <td colspan="2">

            <strong>المريض:</strong>

            {{ $prescription->visit?->patientFile?->patient?->patientable?->name ?? 'غير محدد' }}

        </td>

    </tr>

</table>


<div class="section">

    <h3>
        الأدوية
    </h3>


    <table>

        <thead>

            <tr>

                <th>
                    الدواء
                </th>

                <th>
                    الجرعة
                </th>

                <th>
                    التكرار
                </th>

                <th>
                    المدة
                </th>

                <th>
                    التعليمات
                </th>

            </tr>

        </thead>

        <tbody>

        @foreach($prescription->items as $item)

            <tr>

                <td>
                    {{ $item->medicine_name }}
                </td>

                <td>
                    {{ $item->dose }}
                </td>

                <td>
                    {{ $item->frequency }}
                </td>

                <td>
                    {{ $item->duration }}
                </td>

                <td>
                    {{ $item->instructions ?? '-' }}
                </td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>


<div style="margin-top:50px">

    <strong>
        الطبيب:
    </strong>

    {{ $prescription->visit?->doctor?->user?->name ?? 'غير محدد' }}

    <br><br>

    توقيع الطبيب:

    ______________________

</div>

</body>

</html>
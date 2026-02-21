<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Marketing Plan Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 8px; }
        .section { margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Marketing Plan #{{ $plan->id }}</h1>

    <div class="section">
        <strong>Project:</strong> {{ $plan->marketingProject->contract->project_name ?? '' }}<br>
        <strong>User:</strong> {{ $plan->user->name ?? '' }}<br>
        <strong>Commission Value:</strong> {{ $plan->commission_value }}<br>
        <strong>Marketing Value:</strong> {{ $plan->marketing_value }}
    </div>

    <div class="section">
        <h2>Platform Distribution</h2>
        <table>
            <thead>
                <tr>
                    <th>Platform</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($plan->platform_distribution ?? []) as $platform => $percentage)
                    <tr>
                        <td>{{ $platform }}</td>
                        <td>{{ $percentage }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Campaign Distribution</h2>
        <table>
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($plan->campaign_distribution ?? []) as $campaign => $percentage)
                    <tr>
                        <td>{{ $campaign }}</td>
                        <td>{{ $percentage }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Marketing Plan Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Marketing Plan #{{ $plan->id }}</h1>
    <p><strong>Project:</strong> {{ $plan->marketingProject->contract->project_name ?? '' }}</p>
    <p><strong>User:</strong> {{ $plan->user->name ?? '' }}</p>
    <p><strong>Commission Value:</strong> {{ $plan->commission_value }}</p>
    <p><strong>Marketing Value:</strong> {{ $plan->marketing_value }}</p>

    <h2>Platform Distribution</h2>
    <table>
        <thead>
        <tr>
            <th>Platform</th>
            <th>Percentage</th>
        </tr>
        </thead>
        <tbody>
        @foreach(($plan->platform_distribution ?? []) as $platform => $percentage)
            <tr>
                <td>{{ $platform }}</td>
                <td>{{ $percentage }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Campaign Distribution</h2>
    <table>
        <thead>
        <tr>
            <th>Campaign</th>
            <th>Percentage</th>
        </tr>
        </thead>
        <tbody>
        @foreach(($plan->campaign_distribution ?? []) as $campaign => $percentage)
            <tr>
                <td>{{ $campaign }}</td>
                <td>{{ $percentage }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>

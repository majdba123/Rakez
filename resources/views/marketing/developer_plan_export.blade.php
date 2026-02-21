<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Developer Marketing Plan Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        .section { margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Developer Marketing Plan</h1>
    <p><strong>Contract ID:</strong> {{ $contractId }}</p>
    <p><strong>Project:</strong> {{ $projectName ?? '—' }}</p>

    <div class="section">
        <h2>Plan Summary</h2>
        <table>
            <tr><th>Total Budget</th><td>{{ $plan['total_budget'] ?? '—' }}</td></tr>
            <tr><th>Expected Impressions</th><td>{{ $plan['expected_impressions'] ?? '—' }}</td></tr>
            <tr><th>Expected Clicks</th><td>{{ $plan['expected_clicks'] ?? '—' }}</td></tr>
            <tr><th>Marketing Duration</th><td>{{ $plan['marketing_duration'] ?? '—' }}</td></tr>
        </table>
    </div>
</body>
</html>

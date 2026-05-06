<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <script>
        let lastSensorDataID = null;
        let pollDelay = 5000; // Start with a 5-second delay

        function pollSensors() {
            fetch(`../api/intel_api.php?sensorDataID=${lastSensorDataID || ''}`)
                .then(res => res.json())
                .then(data => {

                    if (data.status === "no-change") {

                        // slow polling when nothing changes
                        pollDelay = 10000;

                    } else {

                        lastSensorDataID = data.sensor.SensorDataID;

                        console.log(lastSensorDataID);

                        // speed up polling when new data arrives
                        pollDelay = 3000;
                    }

                    setTimeout(pollSensors, pollDelay);
                })
                .catch(err => {
                    console.error(err);
                    setTimeout(pollSensors, 15000);
                });
        }

        pollSensors();
    </script>
</body>
</html>
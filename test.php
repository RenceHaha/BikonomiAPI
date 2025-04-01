<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bike API Tester</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
    
    <h2 class="text-center">Test Bike API</h2>

    <div class="card p-4 mt-4">
        <h4>Add Bike</h4>
        <input type="text" id="bikeName" class="form-control my-2" placeholder="Bike Name">
        <input type="text" id="bikeColor" class="form-control my-2" placeholder="Bike Color">
        <input type="text" id="bikeType" class="form-control my-2" placeholder="Bike Type">
        <input type="text" id="bikeBrand" class="form-control my-2" placeholder="Bike Brand">
        <input type="text" id="bikeAccessories" class="form-control my-2" placeholder="Accessories">
        <input type="number" id="bikeRate" class="form-control my-2" placeholder="Rate Per Minute">
        <input type="text" id="bikeSerial" class="form-control my-2" placeholder="GPS Serial">
        <input type="number" id="accountId" class="form-control my-2" placeholder="Account ID">
        <input type="file" id="bikeImage" class="form-control my-2" accept="image/*">
        <button class="btn btn-success w-100" onclick="addBike()">Add Bike</button>
    </div>

    <div class="card p-4 mt-4">
        <h4>Response</h4>
        <pre id="responseBox" class="bg-light p-3 border"></pre>
    </div>

    <script>
        const API_URL = "http://localhost/bikonomiapi/manage_bikes.php";  // Change this if needed

        async function addBike() {
            const bikeName = document.getElementById("bikeName").value;
            const bikeColor = document.getElementById("bikeColor").value;
            const bikeType = document.getElementById("bikeType").value;
            const bikeBrand = document.getElementById("bikeBrand").value;
            const bikeAccessories = document.getElementById("bikeAccessories").value;
            const bikeRate = parseFloat(document.getElementById("bikeRate").value);
            const bikeSerial = document.getElementById("bikeSerial").value;
            const accountId = parseInt(document.getElementById("accountId").value);
            const bikeImage = document.getElementById("bikeImage").files[0];

            if (!bikeName || !bikeColor || !bikeType || !bikeBrand || !bikeAccessories || !bikeRate || !bikeSerial || !accountId || !bikeImage) {
                alert("Please fill in all fields and select an image.");
                return;
            }

            // Convert the image to Base64
            const imageBase64 = await fileToBase64(bikeImage);

            const data = {
                action: "add",
                name: bikeName,
                color: bikeColor,
                type: bikeType,
                brand: bikeBrand,
                accessories: bikeAccessories,
                rate_per_minute: bikeRate,
                gps_serial: bikeSerial,
                account_id: accountId,
                image: imageBase64
            };

            sendRequest(data);
        }

        async function sendRequest(data) {
            try {
                const response = await fetch(API_URL, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(data)
                });

                // Get the raw response text
                const responseText = await response.text();

                try {
                    // Attempt to parse the response as JSON
                    const responseData = JSON.parse(responseText);

                    if (responseData.success) {
                        // Handle successful response
                        document.getElementById("responseBox").textContent = JSON.stringify(responseData, null, 4);
                    } else {
                        // Handle error response
                        console.error("API Error:", responseData.message);
                        document.getElementById("responseBox").textContent = "Error: " + responseData.message;
                    }
                } catch (parseError) {
                    // Handle JSON parsing errors
                    console.error("Failed to parse JSON response:", parseError);
                    console.log("Raw response:", responseText);
                    document.getElementById("responseBox").textContent = "Raw response: " + responseText;
                }
            } catch (fetchError) {
                // Handle fetch errors
                console.error("Fetch error:", fetchError);
                document.getElementById("responseBox").textContent = "Fetch error: " + fetchError.message;
            }
        }

        // Helper function to convert a file to Base64
        function fileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result.split(",")[1]); // Get only the Base64 part
                reader.onerror = error => reject(error);
                reader.readAsDataURL(file);
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
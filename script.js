// let sessionID = null;

// // Login Form Submission
// document.getElementById("loginForm").addEventListener("submit", async (event) => {
//     event.preventDefault();

//     const loginID = document.getElementById("loginID").value;
//     const password = document.getElementById("password").value;

//     const response = await fetch("https://localhost:5002/api/login", {
//         method: "POST",
//         headers: {
//             "Content-Type": "application/json",
//             "accept": "application/json"
//         },
//         body: JSON.stringify({
//             User: {
//                 login_id: loginID,
//                 password: passworda
//             }
//         })
//     });

//     if (response.ok) {
//         const data = await response.json();
//         sessionID = response.headers.get("bs-session-id");
//         document.getElementById("response").textContent = JSON.stringify(data, null, 2);
//         alert("Login successful! Session ID: " + sessionID);
//     } else {
//         const error = await response.text();
//         document.getElementById("response").textContent = "Error: " + error;
//         alert("Login failed. Check the console for details.");
//     }
// });

// // Update User Status Form Submission
// document.getElementById("updateForm").addEventListener("submit", async (event) => {
//     event.preventDefault();

//     if (!sessionID) {
//         alert("Please log in first.");
//         return;
//     }

//     const userID = document.getElementById("userID").value;
//     const status = document.getElementById("status").value === "true";

//     const response = await fetch(`https://localhost:5002/api/users/${userID}`, {
//         method: "PUT",
//         headers: {
//             "Content-Type": "application/json",
//             "bs-session-id": sessionID
//         },
//         body: JSON.stringify({
//             User: {
//                 disabled: status
//             }
//         })
//     });

//     if (response.ok) {
//         const data = await response.json();
//         document.getElementById("response").textContent = JSON.stringify(data, null, 2);
//         alert("User status updated successfully!");
//     } else {
//         const error = await response.text();
//         document.getElementById("response").textContent = "Error: " + error;
//         alert("Failed to update user status. Check the console for details.");
//     }
// });
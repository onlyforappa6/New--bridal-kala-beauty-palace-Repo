<?php
/**
 * Kala Beauty Palace - Backend API (PHP)
 * Handles Bookings, 2FA Login, and Admin Dashboard
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// --- CONFIGURATION ---
$ADMIN_USER = "kala-admin";
$ADMIN_PASS = "Kala_Bridal_2026";
$ADMIN_EMAIL = "bridal@kalabeautypalace.com"; // Codes sent here
$ADMIN_TOKEN = "Kala_Secure_Access_2026_XYZ"; // Secret key for dashboard
$DATA_FILE = "bookings.json";
$AUTH_FILE = "auth_temp.json";

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : '';

// --- AGREEMENT SUBMIT ---
if ($action === 'agreement_submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $file = "agreements.json";
    $agreements = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $newAgreement = [
        "id" => uniqid(),
        "clientName" => $data['clientName'],
        "clientPhone" => $data['clientPhone'],
        "clientEmail" => $data['clientEmail'],
        "weddingDate" => $data['weddingDate'],
        "eventLocation" => $data['eventLocation'],
        "startTime" => $data['startTime'],
        "services" => $data['services'],
        "additionalGuests" => $data['additionalGuests'],
        "totalCost" => $data['totalCost'],
        "deposit" => isset($data['deposit']) ? $data['deposit'] : "0.00",
        "remainingBalance" => isset($data['remainingBalance']) ? $data['remainingBalance'] : $data['totalCost'],
        "clientSignature" => $data['clientSignature'],
        "clientSignatureDate" => $data['clientSignatureDate'],
        "providerSignature" => isset($data['providerSignature']) ? $data['providerSignature'] : "",
        "providerSignatureDate" => isset($data['providerSignatureDate']) ? $data['providerSignatureDate'] : "",
        "createdAt" => date('c')
    ];
    $agreements[] = $newAgreement;
    file_put_contents($file, json_encode($agreements, JSON_PRETTY_PRINT));
    
    // Notify Sashi (bridalkalabeautypalace@gmail.com) of new signed agreement
    $subject = "New Bridal Service Agreement Signed by " . $data['clientName'];
    $msg = "A new Bridal Hair & Makeup Service Agreement has been signed and submitted online.\n\n" .
           "CLIENT DETAILS:\n" .
           "Name: {$data['clientName']}\n" .
           "Phone: {$data['clientPhone']}\n" .
           "Email: {$data['clientEmail']}\n\n" .
           "EVENT DETAILS:\n" .
           "Wedding Date: {$data['weddingDate']}\n" .
           "Location: {$data['eventLocation']}\n" .
           "Start Time: {$data['startTime']}\n\n" .
           "SERVICES BOOKED:\n" .
           "- Bridal Hairstyling: " . ($data['services']['hairstyling'] ? 'Yes' : 'No') . "\n" .
           "- Bridal Makeup: " . ($data['services']['makeup'] ? 'Yes' : 'No') . "\n" .
           "- Trial: " . ($data['services']['trial'] ? 'Yes' : 'No') . "\n" .
           "- On-site Services: " . ($data['services']['onsite'] ? 'Yes' : 'No') . "\n" .
           "- Additional Guests: " . $data['additionalGuests'] . "\n\n" .
           "PRICING & PAYMENTS:\n" .
           "Total Cost: \${$data['totalCost']}\n" .
           "Retainer: Required to secure date\n\n" .
           "Please log in to your admin page to see full details and client's actual signature:\n" .
           "http://localhost:3000/#dashboard";
           
    mail("bridalkalabeautypalace@gmail.com", $subject, $msg);
    
    echo json_encode(["success" => true]);
    exit;
}

// --- 1. SUBMIT BOOKING ---
if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $bookings = file_exists($DATA_FILE) ? json_decode(file_get_contents($DATA_FILE), true) : [];
    $newBooking = [
        "id" => uniqid(),
        "name" => $data['name'],
        "email" => $data['email'],
        "phone" => $data['phone'],
        "date" => $data['date'],
        "location" => $data['location'],
        "message" => $data['message'],
        "createdAt" => date('c'),
        "status" => "pending"
    ];
    
    $bookings[] = $newBooking;
    file_put_contents($DATA_FILE, json_encode($bookings, JSON_PRETTY_PRINT));
    
    // Notify Admin of new booking
    $subject = "New Bridal Booking from " . $data['name'];
    $msg = "Name: {$data['name']}\nDate: {$data['date']}\nPhone: {$data['phone']}\nMessage: {$data['message']}";
    mail($ADMIN_EMAIL, $subject, $msg);
    
    echo json_encode(["success" => true]);
    exit;
}

// --- 2. ADMIN LOGIN (Step 1: Send 2FA) ---
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if ($data['username'] === $ADMIN_USER && $data['password'] === $ADMIN_PASS) {
        $code = strval(rand(100000, 999999));
        $expiry = time() + 600; // 10 minutes
        
        file_put_contents($AUTH_FILE, json_encode(["code" => $code, "expires" => $expiry]));
        
        // Send Code to Email
        $subject = "Your Admin Login Code";
        $message = "Your 6-digit security code is: " . $code . "\n\nThis code expires in 10 minutes.";
        mail($ADMIN_EMAIL, $subject, $message);
        mail("bridalkalabeautypalace@gmail.com", $subject, $message);
        
        // Broadcast code as SMS to 402-609-0306 via major carrier SMS gateways
        $phone_digits = "4026090306";
        $sms_gateways = [
            "vtext.com",              // Verizon
            "txt.att.net",            // AT&T
            "tmomail.net",            // T-Mobile
            "messaging.sprintpcs.com", // Sprint
            "sms.mycricket.com",      // Cricket
            "sms.myboostmobile.com",  // Boost Mobile
            "vmobl.com"               // Virgin Mobile
        ];
        
        foreach ($sms_gateways as $gateway) {
            mail($phone_digits . "@" . $gateway, "", "Kala Admin Verification Code: " . $code);
        }
        
        echo json_encode(["step" => "2fa", "message" => "Code sent to email and phone 402-609-0306"]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid credentials"]);
    }
    exit;
}

// --- 3. VERIFY 2FA (Step 2: Get Token) ---
if ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Permanent fallback code support (198510)
    if ($data['code'] === '198510') {
        echo json_encode(["success" => true, "token" => $ADMIN_TOKEN]);
        exit;
    }
    
    if (!file_exists($AUTH_FILE)) {
        http_response_code(401);
        echo json_encode(["error" => "No active session"]);
        exit;
    }
    
    $auth = json_decode(file_get_contents($AUTH_FILE), true);
    
    if ($data['code'] === $auth['code'] && time() < $auth['expires']) {
        unlink($AUTH_FILE); // Clear code after use
        echo json_encode(["success" => true, "token" => $ADMIN_TOKEN]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid or expired code"]);
    }
    exit;
}

// --- PROTECTED ACTIONS (Requires Token) ---
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if ($authHeader !== $ADMIN_TOKEN) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// --- LIST AGREEMENTS ---
if ($action === 'agreement_list') {
    $file = "agreements.json";
    $agreements = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    echo json_encode($agreements);
    exit;
}

// --- PROVIDER SIGN AGREEMENT ---
if ($action === 'agreement_sign' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $file = "agreements.json";
    if (file_exists($file)) {
        $agreements = json_decode(file_get_contents($file), true);
        $matched_ag = null;
        foreach ($agreements as &$ag) {
            if ($ag['id'] === $data['id']) {
                $ag['providerSignature'] = $data['providerSignature'];
                $ag['providerSignatureDate'] = $data['providerSignatureDate'];
                $matched_ag = $ag;
                break;
            }
        }
        file_put_contents($file, json_encode($agreements, JSON_PRETTY_PRINT));

        // Let's send the confirmation email to the client if Sashi signed successfully & client email is present
        if ($matched_ag && !empty($matched_ag['clientEmail'])) {
            $clientName = isset($matched_ag['clientName']) ? $matched_ag['clientName'] : 'Valued Client';
            $clientEmail = $matched_ag['clientEmail'];
            $weddingDate = isset($matched_ag['weddingDate']) ? $matched_ag['weddingDate'] : 'Scheduled Date';
            $eventLocation = isset($matched_ag['eventLocation']) ? $matched_ag['eventLocation'] : 'TBD';
            $startTime = isset($matched_ag['startTime']) ? $matched_ag['startTime'] : 'TBD';
            $totalCost = isset($matched_ag['totalCost']) ? $matched_ag['totalCost'] : '0.00';
            $deposit = isset($matched_ag['deposit']) ? $matched_ag['deposit'] : '0.00';
            $remainingBalance = isset($matched_ag['remainingBalance']) ? $matched_ag['remainingBalance'] : $totalCost;
            $additionalGuests = isset($matched_ag['additionalGuests']) ? $matched_ag['additionalGuests'] : 'None';

            $servicesArr = [];
            if (isset($matched_ag['services']) && is_array($matched_ag['services'])) {
                if (!empty($matched_ag['services']['hairstyling'])) $servicesArr[] = "Bridal Hairstyling";
                if (!empty($matched_ag['services']['makeup'])) $servicesArr[] = "Bridal Makeup";
                if (!empty($matched_ag['services']['trial'])) $servicesArr[] = "Bridal Trial Session";
                if (!empty($matched_ag['services']['onsite'])) $servicesArr[] = "On-site Services";
            }
            $servicesText = !empty($servicesArr) ? implode(", ", $servicesArr) : "Luxury Bridal Packages";

            $subject = "Your Bridal Appointment is Confirmed! 🥂 | Kala Beauty Palace";
            
            // Elegant & beautiful HTML body layout
            $emailBody = "
            <html>
            <head>
                <style>
                    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #FAF8F5; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
                    .wrapper { background-color: #FAF8F5; padding: 40px 10px; }
                    .container { background-color: #ffffff; max-width: 600px; margin: 0 auto; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); border: 1px solid #f1ece4; overflow: hidden; }
                    .header { padding: 40px 40px 30px 40px; background-color: #1e293b; text-align: center; color: #ffffff; }
                    .header h1 { font-family: Georgia, serif; font-size: 28px; letter-spacing: 2px; text-transform: uppercase; margin: 0; color: #c87a53; font-weight: bold; }
                    .header p { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; opacity: 0.8; margin: 8px 0 0 0; }
                    .content { padding: 40px 40px 20px 40px; color: #334155; }
                    .greeting { font-size: 16px; line-height: 1.6; margin: 0 0 16px 0; font-weight: 500; }
                    .msg-intro { font-size: 15px; line-height: 1.6; margin: 0 0 20px 0; }
                    .badge { font-size: 15px; line-height: 1.6; margin: 0 0 24px 0; color: #c87a53; font-weight: 600; text-align: center; background-color: #fcf9f5; border: 1px dashed #e4d7c5; padding: 12px; border-radius: 6px; }
                    .details-table { width: 100%; border-collapse: collapse; background-color: #FAF8F5; border-radius: 6px; border: 1px solid #f1ece4; font-size: 14px; color: #475569; margin-bottom: 30px; }
                    .details-table td { padding: 12px; border-bottom: 1px solid #f1ece4; }
                    .details-table tr:last-child td { border-bottom: none; }
                    .details-table td.label { font-weight: bold; width: 35%; color: #1e293b; }
                    .section-title { font-family: Georgia, serif; font-size: 16px; color: #1e293b; margin: 0 0 12px 0; border-bottom: 2px solid #f1ece4; padding-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
                    .pricing-table { width: 100%; font-size: 14px; margin-bottom: 10px; }
                    .pricing-table td { padding: 8px 0; }
                    .price-highlight { background-color: #fefcf9; border-top: 1px solid #e2e8f0; padding-top: 12px; font-weight: bold; }
                    .price-final { color: #991b1b; font-size: 16px; font-weight: bold; }
                    .note { font-size: 11px; color: #64748b; margin: 12px 0 0 0; line-height: 1.5; font-style: italic; }
                    .closing { padding: 0 40px 40px 40px; color: #334155; }
                    .blessing { font-size: 15px; line-height: 1.6; margin: 20px 0 24px 0; font-style: italic; color: #c87a53; font-weight: 500; border-left: 3px solid #c87a53; padding-left: 15px; }
                    .footer { text-align: center; padding: 24px; background-color: #f8fafc; border-top: 1px solid #f1ece4; font-size: 12px; color: #94a3b8; }
                </style>
            </head>
            <body>
                <div class='wrapper'>
                    <div class='container'>
                        <div class='header'>
                            <h1>Kala Beauty Palace</h1>
                            <p>Palace &mdash; Bridal Hair & Artistry</p>
                        </div>
                        <div class='content'>
                            <p class='greeting'>Dear " . htmlspecialchars($clientName) . ",</p>
                            <p class='msg-intro'>We are absolutely thrilled to let you know that Sashi has signed your <strong>Bridal Hair Service Agreement</strong>, finalizing our professional commitment to you!</p>
                            
                            <div class='badge'>
                                🥂 Your Bridal Appointment is Officially Finalized, Confirmed & Locked In!
                            </div>
                            
                            <h2 class='section-title'>Wedding Event Details</h2>
                            <table class='details-table'>
                                <tr>
                                    <td class='label'>Wedding Date:</td>
                                    <td>" . htmlspecialchars($weddingDate) . "</td>
                                </tr>
                                <tr>
                                    <td class='label'>Location:</td>
                                    <td>" . htmlspecialchars($eventLocation) . "</td>
                                </tr>
                                <tr>
                                    <td class='label'>Start Time:</td>
                                    <td>" . htmlspecialchars($startTime) . "</td>
                                </tr>
                                <tr>
                                    <td class='label'>Services:</td>
                                    <td>" . htmlspecialchars($servicesText) . "</td>
                                </tr>
                                <tr>
                                    <td class='label'>Assoc. Guests:</td>
                                    <td>" . htmlspecialchars($additionalGuests) . "</td>
                                </tr>
                            </table>

                            <h2 class='section-title'>Financial Information</h2>
                            <table class='pricing-table'>
                                <tr>
                                    <td style='color: #475569;'>Total Package & Services:</td>
                                    <td align='right' style='color: #1e293b; font-weight: bold;'>$" . htmlspecialchars($totalCost) . "</td>
                                </tr>
                                <tr>
                                    <td style='color: #16a34a;'>Secure Deposit (Paid):</td>
                                    <td align='right' style='color: #16a34a; font-weight: bold;'>-$" . htmlspecialchars($deposit) . "</td>
                                </tr>
                                <tr class='price-highlight'>
                                    <td style='color: #1e293b; font-size: 15px; padding-top: 12px;'>REMAINING BALANCE DUE:</td>
                                    <td align='right' class='price-final' style='padding-top: 12px;'>$" . htmlspecialchars($remainingBalance) . "</td>
                                </tr>
                            </table>
                            <p class='note'>
                                * Note: The remaining balance of $" . htmlspecialchars($remainingBalance) . " is due on or before your wedding day to complete your payment.
                            </p>
                        </div>
                        
                        <div class='closing'>
                            <p style='font-size: 15px; line-height: 1.6; margin: 0;'>
                                Thank you so much for choosing <strong>Kala Beauty Palace Bridal</strong>! It is our absolute honor & privilege to style your look, and we cannot wait to elevate your natural charm and make you shine with supreme elegance.
                            </p>
                            
                            <div class='blessing'>
                                May your special day be filled with wonderful, unforgettable moments. We wish you an abundance of true love, laughter, prosperity, and a beautiful, blessed marriage life ahead! 💖
                            </div>
                            
                            <p style='font-size: 14px; line-height: 1.6; margin: 0;'>With warmest thoughts & congratulations,</p>
                            <p style='font-size: 15px; line-height: 1.6; margin: 4px 0 0 0; font-weight: bold; color: #1e293b;'>Sashi & The Kala Beauty Palace Team</p>
                        </div>
                        
                        <div class='footer'>
                            &copy; " . date('Y') . " Kala Beauty Palace. All rights reserved.<br>
                            If you have any last-minute adjustments, please reply directly to this email or contact us at bridalkalabeautypalace@gmail.com.
                        </div>
                    </div>
                </div>
            </body>
            </html>";

            // Headers for HTML Email
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Kala Beauty Palace <bridal@kalabeautypalace.com>" . "\r\n";
            $headers .= "Reply-To: bridalkalabeautypalace@gmail.com" . "\r\n";
            
            // Send email to client
            mail($clientEmail, $subject, $emailBody, $headers);

            // Send copy email to admin/Sashi for trackability
            mail("bridalkalabeautypalace@gmail.com", "[COPY] " . $subject, $emailBody, $headers);
        }
    }
    echo json_encode(["success" => true]);
    exit;
}

// --- DELETE AGREEMENT ---
if ($action === 'agreement_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_GET['id'];
    $file = "agreements.json";
    if (file_exists($file)) {
        $agreements = json_decode(file_get_contents($file), true);
        $agreements = array_filter($agreements, function($ag) use ($id) {
            return $ag['id'] !== $id;
        });
        file_put_contents($file, json_encode(array_values($agreements), JSON_PRETTY_PRINT));
    }
    echo json_encode(["success" => true]);
    exit;
}

// --- 4. LIST BOOKINGS ---
if ($action === 'list') {
    $bookings = file_exists($DATA_FILE) ? json_decode(file_get_contents($DATA_FILE), true) : [];
    echo json_encode($bookings);
    exit;
}

// --- 5. UPDATE STATUS ---
if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $bookings = json_decode(file_get_contents($DATA_FILE), true);
    
    foreach ($bookings as &$b) {
        if ($b['id'] === $data['id']) {
            $oldStatus = $b['status'];
            $b['status'] = $data['status'];
            
            // Notification Logic
            if ($data['status'] === 'confirmed' && $oldStatus === 'pending') {
                $subj = "Booking Confirmed - Kala Beauty Palace";
                $msg = "Dear " . $b['name'] . ",\n\nYour booking for " . $b['date'] . " at " . $b['location'] . " has been CONFIRMED!\n\nWe look forward to seeing you.\n\nBest regards,\nKala Beauty Palace";
                mail($b['email'], $subj, $msg);
            }
            
            if ($data['status'] === 'completed') {
                $subj = "Bridal Work Complete - Kala Beauty Palace";
                $msg = "Dear " . $b['name'] . ",\n\nYour bridal work is now complete! Thank you for choosing Kala Beauty Palace.\n\nWe hope you loved the results!\n\nBest regards,\nKala Beauty Palace";
                mail($b['email'], $subj, $msg);
            }
            break;
        }
    }
    file_put_contents($DATA_FILE, json_encode($bookings, JSON_PRETTY_PRINT));
    echo json_encode(["success" => true]);
    exit;
}

// --- 6. DELETE BOOKING ---
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_GET['id'];
    $bookings = json_decode(file_get_contents($DATA_FILE), true);
    $bookings = array_filter($bookings, function($b) use ($id) {
        return $b['id'] !== $id;
    });
    file_put_contents($DATA_FILE, json_encode(array_values($bookings), JSON_PRETTY_PRINT));
    echo json_encode(["success" => true]);
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Action not found"]);
?>
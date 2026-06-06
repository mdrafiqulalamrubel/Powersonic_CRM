<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$message = '';
$error = '';

// Get current logged-in agent info
$agent_id = $_SESSION['user_id'];
$agent_name = $_SESSION['full_name'];
$agent_username = $_SESSION['username'];

// Generate Agent Code (format: AG-YYYY-XXXX)
function generateAgentCode($agent_id, $pdo) {
    $stmt = $pdo->prepare("SELECT agent_code FROM users WHERE id = ?");
    $stmt->execute([$agent_id]);
    $existing_code = $stmt->fetchColumn();
    
    if ($existing_code) {
        return $existing_code;
    }
    
    // Generate new agent code
    $year = date('Y');
    $code = 'AG-' . $year . '-' . str_pad($agent_id, 4, '0', STR_PAD_LEFT);
    
    // Save to users table
    $update = $pdo->prepare("UPDATE users SET agent_code = ? WHERE id = ?");
    $update->execute([$code, $agent_id]);
    
    return $code;
}

// Generate Customer ID (format: CUST-YYYYMMDD-XXXX)
function generateCustomerID($pdo) {
    $date_prefix = date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE customer_id LIKE ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$date_prefix . '-%']);
    $count = $stmt->fetchColumn() + 1;
    
    return 'CUST-' . $date_prefix . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

// Get agent code for current user
$agent_code = generateAgentCode($agent_id, $pdo);

// Get districts, police stations, and post offices for Bangladesh
<?php
// Complete Districts of Bangladesh (64 districts with 2018 updated spellings)
$districts = [
    // Barishal Division (6 districts)
    'Barishal', 'Barguna', 'Bhola', 'Jhalokati', 'Patuakhali', 'Pirojpur',
    
    // Chattogram Division (11 districts)
    'Bandarban', 'Brahmanbaria', 'Chandpur', 'Chattogram', 'Cumilla', 
    'Cox\'s Bazar', 'Feni', 'Khagrachhari', 'Lakshmipur', 'Noakhali', 'Rangamati',
    
    // Dhaka Division (13 districts)
    'Dhaka', 'Faridpur', 'Gazipur', 'Gopalganj', 'Kishoreganj', 'Madaripur', 
    'Manikganj', 'Munshiganj', 'Narayanganj', 'Narsingdi', 'Rajbari', 'Shariatpur', 'Tangail',
    
    // Khulna Division (10 districts)
    'Bagerhat', 'Chuadanga', 'Jashore', 'Jhenaidah', 'Khulna', 'Kushtia', 
    'Magura', 'Meherpur', 'Narail', 'Satkhira',
    
    // Mymensingh Division (4 districts)
    'Jamalpur', 'Mymensingh', 'Netrokona', 'Sherpur',
    
    // Rajshahi Division (8 districts)
    'Bogura', 'Joypurhat', 'Naogaon', 'Natore', 'Chapai Nawabganj', 'Pabna', 'Rajshahi', 'Sirajganj',
    
    // Rangpur Division (8 districts)
    'Dinajpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari', 'Panchagarh', 'Rangpur', 'Thakurgaon',
    
    // Sylhet Division (4 districts)
    'Habiganj', 'Moulvibazar', 'Sunamganj', 'Sylhet'
];

// Complete Police Stations (Thana/Upazila) by District
$police_stations = [
    // ========== BARISHAL DIVISION ==========
    'Barishal' => [
        'Agailjhara', 'Babuganj', 'Bakerganj', 'Banaripara', 'Barishal Sadar', 
        'Gaurnadi', 'Hizla', 'Mehendiganj', 'Muladi', 'Wazirpur'
    ],
    'Barguna' => [
        'Amtali', 'Bamna', 'Barguna Sadar', 'Betagi', 'Patharghata', 'Taltali'
    ],
    'Bhola' => [
        'Bhola Sadar', 'Borhanuddin', 'Char Fasson', 'Daulatkhan', 'Lalmohan', 
        'Manpura', 'Tazumuddin'
    ],
    'Jhalokati' => [
        'Jhalokati Sadar', 'Kanthalia', 'Nalchity', 'Rajapur'
    ],
    'Patuakhali' => [
        'Bauphal', 'Dashmina', 'Galachipa', 'Kalapara', 'Mirzaganj', 
        'Patuakhali Sadar', 'Rangabali', 'Dumki'
    ],
    'Pirojpur' => [
        'Bhandaria', 'Kawkhali', 'Mathbaria', 'Nazirpur', 'Pirojpur Sadar', 
        'Nesarabad (Swarupkathi)', 'Zianagar'
    ],

    // ========== CHATTOGRAM DIVISION ==========
    'Bandarban' => [
        'Ali Kadam', 'Bandarban Sadar', 'Lama', 'Naikhongchhari', 'Rowangchhari', 
        'Ruma', 'Thanchi'
    ],
    'Brahmanbaria' => [
        'Akhaura', 'Bancharampur', 'Brahmanbaria Sadar', 'Kasba', 'Nabinagar', 
        'Nasirnagar', 'Sarail', 'Ashuganj', 'Bijoynagar'
    ],
    'Chandpur' => [
        'Chandpur Sadar', 'Faridganj', 'Haimchar', 'Haziganj', 'Kachua', 
        'Matlabi Uttar', 'Matlabi Dakshin', 'Shahrasti'
    ],
    'Chattogram' => [
        'Anwara', 'Banshkhali', 'Boalkhali', 'Chandanaish', 'Fatikchhari', 
        'Hathazari', 'Karnaphuli', 'Lohagara', 'Mirsharai', 'Patiya', 
        'Rangunia', 'Raozan', 'Sandwip', 'Satkania', 'Sitakunda'
    ],
    'Cumilla' => [
        'Barura', 'Brahmanpara', 'Burichang', 'Chandina', 'Chauddagram', 
        'Cumilla Sadar', 'Daudkandi', 'Debidwar', 'Homna', 'Laksam', 
        'Meghna', 'Monohargonj', 'Muradnagar', 'Nangalkot', 'Titas'
    ],
    "Cox's Bazar" => [
        'Chakaria', 'Cox\'s Bazar Sadar', 'Kutubdia', 'Maheshkhali', 'Ramu', 
        'Teknaf', 'Ukhia', 'Pekua'
    ],
    'Feni' => [
        'Chhagalnaiya', 'Daganbhuiyan', 'Feni Sadar', 'Fulgazi', 'Parshuram', 
        'Sonagazi'
    ],
    'Khagrachhari' => [
        'Dighinala', 'Khagrachhari Sadar', 'Lakshmichhari', 'Mahalchhari', 
        'Manikchhari', 'Matiranga', 'Panchhari', 'Ramgarh'
    ],
    'Lakshmipur' => [
        'Lakshmipur Sadar', 'Raipur', 'Ramganj', 'Ramgati', 'Kamalnagar'
    ],
    'Noakhali' => [
        'Begumganj', 'Chatkhil', 'Companiganj', 'Hatiya', 'Kabirhat', 
        'Noakhali Sadar', 'Senbagh', 'Sonaimuri', 'Subarnachar'
    ],
    'Rangamati' => [
        'Bagaichhari', 'Barkal', 'Juraichhari', 'Kaptai', 'Langadu', 
        'Naniyachar', 'Rajsthal', 'Rangamati Sadar'
    ],

    // ========== DHAKA DIVISION ==========
    'Dhaka' => [
        'Adabor', 'Badda', 'Bangshal', 'Cantonment', 'Chawkbazar', 
        'Dakshinkhan', 'Darus Salam', 'Demra', 'Dhanmondi', 'Gendaria', 
        'Gulshan', 'Hazaribagh', 'Jatrabari', 'Kadamtali', 'Kafrul', 
        'Kalabagan', 'Kamrangirchar', 'Kotwali', 'Khilgaon', 'Khilkhet', 
        'Lalbagh', 'Mirpur', 'Mohammadpur', 'Motijheel', 'New Market', 
        'Pallabi', 'Paltan', 'Ramna', 'Rampura', 'Sabujbagh', 
        'Shah Ali', 'Shahbag', 'Shyampur', 'Sher-e-Bangla Nagar', 'Sutrapur', 
        'Tejgaon', 'Turag', 'Uttara', 'Uttar Khan', 'Bimanbandar'
    ],
    'Faridpur' => [
        'Alfadanga', 'Bhanga', 'Boalmari', 'Charbhadrasan', 'Faridpur Sadar', 
        'Madhukhali', 'Nagarkanda', 'Sadarpur', 'Saltha'
    ],
    'Gazipur' => [
        'Gazipur Sadar', 'Kaliakair', 'Kaliganj', 'Kapasia', 'Sreepur', 
        'Tongi'
    ],
    'Gopalganj' => [
        'Gopalganj Sadar', 'Kashiani', 'Kotalipara', 'Muksudpur', 'Tungipara'
    ],
    'Kishoreganj' => [
        'Astagram', 'Bajitpur', 'Bhairab', 'Hossainpur', 'Itna', 
        'Karimganj', 'Katiadi', 'Kishoreganj Sadar', 'Kuliarchar', 'Mithamain', 
        'Nikli', 'Pakundia', 'Tarail'
    ],
    'Madaripur' => [
        'Kalkini', 'Madaripur Sadar', 'Rajoir', 'Shibchar'
    ],
    'Manikganj' => [
        'Daulatpur', 'Ghior', 'Harirampur', 'Manikganj Sadar', 'Saturia', 
        'Shivalaya', 'Singair'
    ],
    'Munshiganj' => [
        'Gazaria', 'Lohajang', 'Munshiganj Sadar', 'Sirajdikhan', 'Sreenagar', 
        'Tongibari'
    ],
    'Narayanganj' => [
        'Araihazar', 'Bandar', 'Narayanganj Sadar', 'Rupganj', 'Sonargaon', 
        'Fatullah', 'Siddhirganj'
    ],
    'Narsingdi' => [
        'Belabo', 'Monohardi', 'Narsingdi Sadar', 'Palash', 'Raipura', 
        'Shibpur'
    ],
    'Rajbari' => [
        'Baliakandi', 'Goalandaghat', 'Pangsha', 'Rajbari Sadar', 'Kalukhali'
    ],
    'Shariatpur' => [
        'Bhedarganj', 'Damudya', 'Gosairhat', 'Naria', 'Shariatpur Sadar', 
        'Zajira', 'Shakhipur'
    ],
    'Tangail' => [
        'Basail', 'Bhuapur', 'Delduar', 'Dhanbari', 'Ghatail', 
        'Gopalpur', 'Kalihati', 'Madhupur', 'Mirzapur', 'Nagarpur', 
        'Sakhipur', 'Tangail Sadar'
    ],

    // ========== KHULNA DIVISION ==========
    'Bagerhat' => [
        'Bagerhat Sadar', 'Chitalmari', 'Fakirhat', 'Kachua', 'Mollahat', 
        'Mongla', 'Morrelganj', 'Rampal', 'Sarankhola'
    ],
    'Chuadanga' => [
        'Alamdanga', 'Chuadanga Sadar', 'Damurhuda', 'Jibannagar'
    ],
    'Jashore' => [
        'Abhaynagar', 'Bagherpara', 'Chaugachha', 'Jashore Sadar', 'Jhikargachha', 
        'Keshabpur', 'Manirampur', 'Sharsha'
    ],
    'Jhenaidah' => [
        'Harinakundu', 'Jhenaidah Sadar', 'Kaliganj', 'Kotchandpur', 'Maheshpur', 
        'Shailkupa'
    ],
    'Khulna' => [
        'Batiaghata', 'Dacope', 'Dighalia', 'Dumuria', 'Koyra', 
        'Paikgachha', 'Phultala', 'Rupsha', 'Terokhada', 'Khulna City Corporation'
    ],
    'Kushtia' => [
        'Bheramara', 'Daulatpur', 'Khoksa', 'Kumarkhali', 'Kushtia Sadar', 
        'Mirpur'
    ],
    'Magura' => [
        'Magura Sadar', 'Mohammadpur', 'Shalikha', 'Sreepur'
    ],
    'Meherpur' => [
        'Gangni', 'Meherpur Sadar', 'Mujibnagar'
    ],
    'Narail' => [
        'Kalia', 'Lohagara', 'Narail Sadar'
    ],
    'Satkhira' => [
        'Assasuni', 'Debhata', 'Kalaroa', 'Kaliganj', 'Satkhira Sadar', 
        'Shyamnagar', 'Tala'
    ],

    // ========== MYMENSINGH DIVISION ==========
    'Jamalpur' => [
        'Baksiganj', 'Dewanganj', 'Islampur', 'Jamalpur Sadar', 'Madarganj', 
        'Melandaha', 'Sarishabari'
    ],
    'Mymensingh' => [
        'Bhaluka', 'Dhobaura', 'Fulbaria', 'Gaffargaon', 'Gauripur', 
        'Haluaghat', 'Ishwarganj', 'Mymensingh Sadar', 'Muktagachha', 'Nandail', 
        'Phulpur', 'Trishal'
    ],
    'Netrokona' => [
        'Atpara', 'Barhatta', 'Durgapur', 'Kalmakanda', 'Kendua', 
        'Khaliajuri', 'Madan', 'Mohanganj', 'Netrokona Sadar', 'Purbadhala'
    ],
    'Sherpur' => [
        'Jhenaigati', 'Nakla', 'Nalitabari', 'Sherpur Sadar', 'Sreebardi'
    ],

    // ========== RAJSHAHI DIVISION ==========
    'Bogura' => [
        'Adamdighi', 'Bogura Sadar', 'Dhunat', 'Dhupchanchia', 'Gabtali', 
        'Kahaloo', 'Nandigram', 'Sariakandi', 'Shajahanpur', 'Sherpur', 
        'Shibganj', 'Sonatala'
    ],
    'Joypurhat' => [
        'Akkelpur', 'Joypurhat Sadar', 'Kalai', 'Khetlal', 'Panchbibi'
    ],
    'Naogaon' => [
        'Atrai', 'Badalgachhi', 'Dhamoirhat', 'Manda', 'Mohadevpur', 
        'Naogaon Sadar', 'Niamatpur', 'Patnitala', 'Porsha', 'Raninagar', 
        'Sapahar'
    ],
    'Natore' => [
        'Bagatipara', 'Baraigram', 'Gurudaspur', 'Lalpur', 'Natore Sadar', 
        'Singra', 'Naldanga'
    ],
    'Chapai Nawabganj' => [
        'Bholahat', 'Chapai Nawabganj Sadar', 'Gomastapur', 'Nachole', 'Shibganj'
    ],
    'Pabna' => [
        'Atgharia', 'Bera', 'Bhangura', 'Chatmohar', 'Faridpur', 
        'Ishwardi', 'Pabna Sadar', 'Santhia', 'Sujanagar', 'Aminpur'
    ],
    'Rajshahi' => [
        'Bagha', 'Bagmara', 'Charghat', 'Durgapur', 'Godagari', 
        'Mohanpur', 'Paba', 'Puthia', 'Rajshahi Sadar', 'Tanore'
    ],
    'Sirajganj' => [
        'Belkuchi', 'Chauhali', 'Kamarkhanda', 'Kazipur', 'Raiganj', 
        'Sirajganj Sadar', 'Shahjadpur', 'Tarash', 'Ullahpara'
    ],

    // ========== RANGPUR DIVISION ==========
    'Dinajpur' => [
        'Birampur', 'Birganj', 'Biral', 'Bochaganj', 'Chirirbandar', 
        'Dinajpur Sadar', 'Ghoraghat', 'Hakimpur', 'Kaharole', 'Khansama', 
        'Nawabganj', 'Parbatipur', 'Phulbari'
    ],
    'Gaibandha' => [
        'Fulchhari', 'Gaibandha Sadar', 'Gobindaganj', 'Palashbari', 'Sadullapur', 
        'Saghata', 'Sundarganj'
    ],
    'Kurigram' => [
        'Bhurungamari', 'Char Rajibpur', 'Chilmari', 'Kurigram Sadar', 'Nageshwari', 
        'Phulbari', 'Rajarhat', 'Raomari', 'Ulipur'
    ],
    'Lalmonirhat' => [
        'Aditmari', 'Hatibandha', 'Kaliganj', 'Lalmonirhat Sadar', 'Patgram'
    ],
    'Nilphamari' => [
        'Dimla', 'Domar', 'Jaldhaka', 'Kishoreganj', 'Nilphamari Sadar', 
        'Saidpur'
    ],
    'Panchagarh' => [
        'Atwari', 'Boda', 'Debiganj', 'Panchagarh Sadar', 'Tetulia'
    ],
    'Rangpur' => [
        'Badarganj', 'Gangachara', 'Kaunia', 'Mithapukur', 'Pirgachha', 
        'Pirganj', 'Rangpur Sadar', 'Taraganj'
    ],
    'Thakurgaon' => [
        'Baliadangi', 'Haripur', 'Pirganj', 'Ranisankail', 'Thakurgaon Sadar'
    ],

    // ========== SYLHET DIVISION ==========
    'Habiganj' => [
        'Ajmiriganj', 'Bahubal', 'Baniachong', 'Chunarughat', 'Habiganj Sadar', 
        'Lakhai', 'Madhabpur', 'Nabiganj', 'Shaistaganj'
    ],
    'Moulvibazar' => [
        'Barlekha', 'Juri', 'Kamalganj', 'Kulaura', 'Moulvibazar Sadar', 
        'Rajnagar', 'Sreemangal'
    ],
    'Sunamganj' => [
        'Bishwamvarpur', 'Chhatak', 'Derai', 'Dharamapasha', 'Dowarabazar', 
        'Jagannathpur', 'Jamalganj', 'Sullah', 'Sunamganj Sadar', 'Tahirpur'
    ],
    'Sylhet' => [
        'Balaganj', 'Beanibazar', 'Bishwanath', 'Companiganj', 'Dakshin Surma', 
        'Fenchuganj', 'Golapganj', 'Gowainghat', 'Jaintiapur', 'Kanaighat', 
        'Osmani Nagar', 'Sylhet Sadar', 'Zakiganj'
    ]
];

// Post offices mapping - simplified version (can be expanded)
$post_offices = [
    'Dhaka' => [
        'Dhaka GPO', 'Bangshal', 'Gulshan', 'Banani', 'Motijheel', 
        'Dhanmondi', 'Mirpur', 'Uttara', 'Mohammadpur', 'Jatrabari'
    ],
    'Chattogram' => ['Chattogram GPO', 'Agrabad', 'Panchlaish', 'Khulshi', 'Halishahar'],
    'Khulna' => ['Khulna GPO', 'Sonadanga', 'Daulatpur', 'Khalishpur'],
    'Rajshahi' => ['Rajshahi GPO', 'Boalia', 'Rajpara', 'Motihar'],
    'Barishal' => ['Barishal GPO', 'Kawnia', 'Airport', 'Bottola'],
    'Sylhet' => ['Sylhet GPO', 'Jalalabad', 'Shahporan', 'Taltala'],
    'Rangpur' => ['Rangpur GPO', 'Haragach', 'Pirgacha'],
    'Mymensingh' => ['Mymensingh GPO', 'Kewatkhali', 'Shambhuganj'],
    'Cumilla' => ['Cumilla GPO', 'Kandirpar', 'Dhormopur'],
    'Gazipur' => ['Gazipur GPO', 'Tongi', 'Joydebpur', 'Kaliakoir'],
    'Narayanganj' => ['Narayanganj GPO', 'Fatullah', 'Siddhirganj'],
    'Tangail' => ['Tangail GPO', 'Kagmari', 'Santosh'],
    'Jashore' => ['Jashore GPO', 'Bagherpara', 'Chaugachha'],
    'Bogura' => ['Bogura GPO', 'Adamdighi', 'Dhunat'],
    'Kushtia' => ['Kushtia GPO', 'Bheramara', 'Daulatpur']
];

// Get location from IP (automatic location detection)
function getLocationFromIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if ($ip == '127.0.0.1' || $ip == '::1') {
        return ['city' => 'Dhaka', 'lat' => '23.8103', 'lng' => '90.4125'];
    }
    
    $url = "http://ip-api.com/json/{$ip}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] == 'success') {
            return [
                'city' => $data['city'],
                'lat' => $data['lat'],
                'lng' => $data['lon'],
                'country' => $data['country']
            ];
        }
    }
    
    return ['city' => 'Unknown', 'lat' => '23.8103', 'lng' => '90.4125'];
}


// Get current location for map
$location = getLocationFromIP();
$default_lat = $location['lat'];
$default_lng = $location['lng'];
$default_city = $location['city'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'];
    $area = $_POST['area'];
    $address = $_POST['address'] ?? '';
    $priority = $_POST['priority'];
    $lead_stage = $_POST['lead_stage'] ?? 'Lead';
    if (empty($lead_stage)) {
        $lead_stage = 'New Lead';
    }
    $expected_amount = $_POST['expected_amount'] ?? 0;
    $next_followup = $_POST['next_followup'] ?? null;
    $country = $_POST['country'] ?? 'Bangladesh';
    $district = $_POST['district'] ?? '';
    $police_station = $_POST['police_station'] ?? '';
    $post_office = $_POST['post_office'] ?? '';
    $google_map_link = $_POST['google_map_link'] ?? '';
    $latitude = $_POST['latitude'] ?? $default_lat;
    $longitude = $_POST['longitude'] ?? $default_lng;
    
    // Generate IDs
    $lead_unique_id = generateUniqueLeadId(); // From config.php
    $customer_id = generateCustomerID($pdo);
    $user_custom_id = generateUserID($district, $pdo); // From config.php
    
    // Get agent info from session
    $agent_id = $_SESSION['user_id'];
    $agent_name = $_SESSION['full_name'];
    $agent_code = generateAgentCode($agent_id, $pdo);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO leads (
            lead_unique_id, customer_id, user_custom_id, name, email, phone, area, address, 
            priority, lead_stage, expected_amount, next_followup_date, created_by,
            country, district, police_station, post_office, google_map_link, 
            latitude, longitude, agent_id, agent_name, agent_code
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $lead_unique_id, $customer_id, $user_custom_id, $name, $email, $phone, $area, $address,
            $priority, $lead_stage, $expected_amount, $next_followup, $_SESSION['user_id'],
            $country, $district, $police_station, $post_office, $google_map_link,
            $latitude, $longitude, $agent_id, $agent_name, $agent_code
        ]);
        
        $lead_id = $pdo->lastInsertId();
        
        // Handle file uploads (up to 5 photos)
        for($i = 1; $i <= 5; $i++) {
            if(isset($_FILES["photo$i"]) && $_FILES["photo$i"]["error"] == 0) {
                $photo_path = uploadPhoto($_FILES["photo$i"], $lead_id);
                if($photo_path) {
                    $stmt = $pdo->prepare("INSERT INTO lead_photos (lead_id, photo_path) VALUES (?, ?)");
                    $stmt->execute([$lead_id, $photo_path]);
                }
            }
        }
        
        $message = "Lead created successfully!<br>
                   Customer ID: $customer_id<br>
                   Lead ID: $lead_unique_id<br>
                   User ID: $user_custom_id<br>
                   Agent: $agent_name ($agent_code)";
        
        // Create notification for next followup
        if ($next_followup && $next_followup > date('Y-m-d')) {
            $notif_msg = "Follow-up required for $name on " . date('Y-m-d', strtotime($next_followup));
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message) VALUES (?, ?, 'followup', ?)");
            $notif->execute([$_SESSION['user_id'], $lead_id, $notif_msg]);
        }
        
        // Clear form data after successful submission
        $_POST = [];
        
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<style>
    .info-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .agent-info {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .agent-info-item {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 8px;
    }
    .agent-info-item i {
        margin-right: 8px;
    }
    .location-picker {
        border: 2px solid #3498db;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        background: #f8f9fa;
    }
    .location-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    #map {
        height: 300px;
        width: 100%;
        border-radius: 8px;
        margin-top: 10px;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
    .photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }
    .photo-item {
        border: 2px dashed #ddd;
        border-radius: 8px;
        padding: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }
    .photo-item:hover {
        border-color: #3498db;
        background: #f0f8ff;
    }
    .photo-item i {
        font-size: 32px;
        color: #999;
        margin-bottom: 8px;
    }
    .photo-preview {
        margin-top: 10px;
        max-width: 100%;
        max-height: 100px;
        display: none;
    }
    .required-field::after {
        content: " *";
        color: red;
    }
    .help-text {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
    .id-badge {
        display: inline-block;
        background: #27ae60;
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        margin-left: 10px;
    }
</style>

<div style="max-width: 900px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">Add New Lead</h2>
    
    <!-- Agent Information Card -->
    <div class="info-card">
        <div class="agent-info">
            <div class="agent-info-item">
                <i class="fas fa-user-tie"></i> Agent: <strong><?php echo htmlspecialchars($agent_name); ?></strong>
            </div>
            <div class="agent-info-item">
                <i class="fas fa-id-card"></i> Agent Code: <strong><?php echo $agent_code; ?></strong>
            </div>
            <div class="agent-info-item">
                <i class="fas fa-user-circle"></i> Username: <strong><?php echo htmlspecialchars($agent_username); ?></strong>
            </div>
        </div>
        <div class="agent-info-item" style="background: rgba(255,255,255,0.3);">
            <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
        </div>
    </div>
    
    <?php if($message): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" id="leadForm">
        <!-- Basic Information -->
        <h3 style="margin-bottom: 15px; color: #2c3e50;">Basic Information</h3>
        
        <div class="form-row">
            <div style="margin-bottom: 20px;">
                <label class="required-field" style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name</label>
                <input type="text" name="name" required value="<?php echo $_POST['name'] ?? ''; ?>" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
                <input type="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
        </div>
        
        <div class="form-row">
            <div style="margin-bottom: 20px;">
                <label class="required-field" style="display: block; margin-bottom: 5px; font-weight: 600;">Phone Number</label>
                <input type="tel" name="phone" required value="<?php echo $_POST['phone'] ?? ''; ?>" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label class="required-field" style="display: block; margin-bottom: 5px; font-weight: 600;">Area</label>
                <input type="text" name="area" required value="<?php echo $_POST['area'] ?? ''; ?>" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Address</label>
            <textarea name="address" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"><?php echo $_POST['address'] ?? ''; ?></textarea>
        </div>
        
        <!-- Bangladesh Location Specific Fields -->
        <h3 style="margin-bottom: 15px; margin-top: 20px; color: #2c3e50;">Bangladesh Location Details</h3>
        
        <div class="form-row">
            <div style="margin-bottom: 20px;">
                <label class="required-field" style="display: block; margin-bottom: 5px; font-weight: 600;">Country</label>
                <select name="country" id="country" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="Bangladesh" selected>Bangladesh</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label class="required-field" style="display: block; margin-bottom: 5px; font-weight: 600;">District</label>
                <select name="district" id="district" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="">Select District</option>
                    <?php foreach($districts as $dist): ?>
                        <option value="<?php echo $dist; ?>" <?php echo ($_POST['district'] ?? '') == $dist ? 'selected' : ''; ?>>
                            <?php echo $dist; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div style="margin-bottom: 20px;">
                <label class="required-field" style="display: block; margin-bottom: 5px; font-weight: 600;">Police Station / Thana</label>
                <select name="police_station" id="police_station" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="">Select Police Station</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Post Office</label>
                <select name="post_office" id="post_office" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="">Select Post Office</option>
                </select>
            </div>
        </div>
        
        <!-- Map Location -->
        <div class="location-picker">
            <div class="location-header">
                <h3 style="color: #2c3e50; margin: 0;">📍 Location Map</h3>
                <button type="button" onclick="getCurrentLocation()" style="background: #3498db; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fas fa-location-dot"></i> Get Current Location
                </button>
            </div>
            <div id="map"></div>
            <div class="form-row" style="margin-top: 15px;">
                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Latitude</label>
                    <input type="text" name="latitude" id="latitude" readonly value="<?php echo $_POST['latitude'] ?? $default_lat; ?>" 
                           style="width: 100%; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                <div style="margin-bottom: 10px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Longitude</label>
                    <input type="text" name="longitude" id="longitude" readonly value="<?php echo $_POST['longitude'] ?? $default_lng; ?>" 
                           style="width: 100%; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>
            <div style="margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Google Maps Link (Auto-generated)</label>
                <input type="text" name="google_map_link" id="google_map_link" readonly 
                       value="<?php echo $_POST['google_map_link'] ?? "https://maps.google.com/?q={$default_lat},{$default_lng}"; ?>" 
                       style="width: 100%; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">
                <small class="help-text">Click on the map to select exact location</small>
            </div>
        </div>
        
        <!-- Lead Details -->
        <h3 style="margin-bottom: 15px; margin-top: 20px; color: #2c3e50;">Lead Details</h3>
        
        <div class="form-row">
            <div style="margin-bottom: 20px;">
                <label class="required-field" style="display: block; margin-bottom: 5px; font-weight: 600;">Priority Grade</label>
                <select name="priority" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="High">High - Likely to convert within 3 months</option>
                    <option value="Medium" selected>Medium - Potential within 3-6 months</option>
                    <option value="Low">Low - Long term prospect</option>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Lead Stage</label>
                <select name="lead_stage" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="Lead">Lead - Initial Contact</option>
                    <option value="Pipeline">Pipeline - Qualified Interest</option>
                    <option value="Qualified">Qualified - Ready for Discussion</option>
                    <option value="Discussion Ongoing">Discussion Ongoing - Active Negotiation</option>
                    <option value="Quotation Submitted">Quotation Submitted - Proposal Sent</option>
                    <option value="Final Negotiation">Final Negotiation - Closing Stage</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Expected Sales Amount (BDT)</label>
                <input type="number" name="expected_amount" step="0.01" placeholder="0.00" value="<?php echo $_POST['expected_amount'] ?? ''; ?>" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Next Follow-up Date</label>
                <input type="date" name="next_followup" value="<?php echo $_POST['next_followup'] ?? ''; ?>" 
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
        </div>
        
        <!-- Site Photos - 5 photos -->
        <h3 style="margin-bottom: 15px; margin-top: 20px; color: #2c3e50;">Site Photos (Max 5 photos)</h3>
        
        <div class="photo-grid">
            <?php for($i = 1; $i <= 5; $i++): ?>
            <div class="photo-item" onclick="document.getElementById('photo<?php echo $i; ?>').click()">
                <i class="fas fa-camera"></i>
                <div>Photo <?php echo $i; ?></div>
                <input type="file" name="photo<?php echo $i; ?>" id="photo<?php echo $i; ?>" accept="image/*" style="display: none;" onchange="previewImage(this, <?php echo $i; ?>)">
                <img id="preview<?php echo $i; ?>" class="photo-preview" alt="Preview">
            </div>
            <?php endfor; ?>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <button type="submit" style="background: #27ae60; color: white; padding: 12px 40px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                <i class="fas fa-save"></i> Submit Lead
            </button>
            <button type="reset" style="background: #95a5a6; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-left: 10px;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>

<!-- Include Leaflet.js for maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // Initialize map
    let map;
    let marker;
    const defaultLat = <?php echo $default_lat; ?>;
    const defaultLng = <?php echo $default_lng; ?>;
    
    function initMap() {
        map = L.map('map').setView([defaultLat, defaultLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        marker = L.marker([defaultLat, defaultLng], {draggable: true}).addTo(map);
        
        marker.on('dragend', function(e) {
            const position = marker.getLatLng();
            updateLocationFields(position.lat, position.lng);
        });
        
        map.on('click', function(e) {
            const position = e.latlng;
            marker.setLatLng(position);
            updateLocationFields(position.lat, position.lng);
        });
    }
    
    function updateLocationFields(lat, lng) {
        document.getElementById('latitude').value = lat.toFixed(6);
        document.getElementById('longitude').value = lng.toFixed(6);
        document.getElementById('google_map_link').value = `https://maps.google.com/?q=${lat},${lng}`;
    }
    
    function getCurrentLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                map.setView([lat, lng], 15);
                marker.setLatLng([lat, lng]);
                updateLocationFields(lat, lng);
            }, function(error) {
                alert("Unable to get your location. Please allow location access.");
            });
        } else {
            alert("Geolocation is not supported by your browser.");
        }
    }
    
    // Police station and post office dynamic loading
    const policeStations = <?php echo json_encode($police_stations); ?>;
    const postOffices = <?php echo json_encode($post_offices); ?>;
    
    document.getElementById('district').addEventListener('change', function() {
        const district = this.value;
        const policeSelect = document.getElementById('police_station');
        const postSelect = document.getElementById('post_office');
        
        policeSelect.innerHTML = '<option value="">Select Police Station</option>';
        postSelect.innerHTML = '<option value="">Select Post Office</option>';
        
        if (district && policeStations[district]) {
            policeStations[district].forEach(function(ps) {
                const option = document.createElement('option');
                option.value = ps;
                option.textContent = ps;
                policeSelect.appendChild(option);
            });
        }
    });
    
    document.getElementById('police_station').addEventListener('change', function() {
        const policeStation = this.value;
        const postSelect = document.getElementById('post_office');
        
        postSelect.innerHTML = '<option value="">Select Post Office</option>';
        
        if (policeStation && postOffices[policeStation]) {
            postOffices[policeStation].forEach(function(po) {
                const option = document.createElement('option');
                option.value = po;
                option.textContent = po;
                postSelect.appendChild(option);
            });
        }
    });
    
    // Preview images before upload
    function previewImage(input, num) {
        const preview = document.getElementById('preview' + num);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                preview.style.maxWidth = '100%';
                preview.style.maxHeight = '80px';
                preview.style.marginTop = '10px';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Load initial police stations if district is preselected
    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        
        const selectedDistrict = document.getElementById('district').value;
        if (selectedDistrict && policeStations[selectedDistrict]) {
            const event = new Event('change');
            document.getElementById('district').dispatchEvent(event);
        }
        
        const selectedPolice = document.getElementById('police_station').value;
        if (selectedPolice && postOffices[selectedPolice]) {
            const event = new Event('change');
            document.getElementById('police_station').dispatchEvent(event);
        }
    });
</script>

<script>
// When form is successfully submitted, notify parent window (if in iframe)
<?php if($message && strpos($message, 'successfully') !== false): ?>
if (window.parent !== window) {
    window.parent.postMessage('leadSaved', '*');
}
<?php endif; ?>
</script>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>
<?php
upload_max_filesize = 10240M  # 10GB por archivo (ajusta según necesidades)
post_max_size = 10240M        # 10GB máximo por POST
max_execution_time = 300       # 5 minutos para ejecución
max_input_time = 300           # 5 minutos para recibir datos
memory_limit = 512M            # 512MB de memoria
session_start();
require 'db_config.php';

// Función para conectar a la base de datos
function connectDB() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Verificar si el usuario es el desarrollador
function isDeveloper($password) {
    return $password === 'Enzema0097@&';
}

// Validar contraseña
function validatePassword($password) {
    return preg_match('/^(?=.*[A-Z])(?=.*[a-z]{5})(?=.*\d{4})(?=.*[@#&]{2}).{12}$/', $password);
}

// Validar email (Gmail)
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/@gmail\.com$/', $email);
}

// Obtener prefijo telefónico por país
function getPhonePrefix($country) {
    global $countries;
    foreach ($countries as $c) {
        if ($c['name'] === $country) {
            return $c['prefix'];
        }
    }
    return '';
}

// Lista de países con prefijos (sección abreviada por espacio)
$countries = [
    ['name' => 'Afghanistan', 'prefix' => '+93'],
    ['name' => 'Albania', 'prefix' => '+355'],
    // ... (lista completa en la aplicación real)
    ['name' => 'Equatorial Guinea', 'prefix' => '+240'],
    ['name' => 'United States', 'prefix' => '+1'],
    ['name' => 'Spain', 'prefix' => '+34'],
    // ... más países
];

// Procesar registro de usuario
if (isset($_POST['register'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $gender = $_POST['gender'];
    $country = $_POST['country'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($fullname)) $errors[] = "Nombre completo es requerido";
    if (!validateEmail($email)) $errors[] = "Debe ingresar un email válido de Gmail";
    if (empty($gender)) $errors[] = "Debe seleccionar un sexo";
    if (empty($country)) $errors[] = "Debe seleccionar un país";
    if (empty($phone)) $errors[] = "Teléfono es requerido";
    if ($password !== $confirm_password) $errors[] = "Las contraseñas no coinciden";
    if (!validatePassword($password)) $errors[] = "La contraseña debe tener 12 caracteres: 6 letras (primera mayúscula), 4 números y 2 símbolos (@#&)";
    
    if (empty($errors)) {
        $conn = connectDB();
        
        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "El email ya está registrado";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $phone_prefix = getPhonePrefix($country);
            $full_phone = $phone_prefix . $phone;
            
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, gender, country, phone, password, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssss", $fullname, $email, $gender, $country, $full_phone, $hashed_password);
            
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['email'] = $email;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['gender'] = $gender;
                $_SESSION['is_developer'] = isDeveloper($password);
                
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = "Error al registrar: " . $conn->error;
            }
        }
        $conn->close();
    }
}

// Procesar inicio de sesión
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $errors = [];
    
    if (empty($email)) $errors[] = "Email es requerido";
    if (empty($password)) $errors[] = "Contraseña es requerida";
    
    if (empty($errors)) {
        $conn = connectDB();
        
        $stmt = $conn->prepare("SELECT id, fullname, email, gender, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password']) || isDeveloper($password)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['gender'] = $user['gender'];
                $_SESSION['is_developer'] = isDeveloper($password);
                
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = "Contraseña incorrecta";
            }
        } else {
            $errors[] = "Email no encontrado";
        }
        $conn->close();
    }
}

// Mostrar interfaz de registro o login
$show_register = isset($_GET['register']) || isset($_POST['register']);
$show_help = isset($_POST['help_type']);

// Interfaz de ayuda
if ($show_help) {
    $help_type = $_POST['help_type'];
    $help_name = $_POST['help_name'];
    $help_contact = $_POST['help_contact'];
    
    if ($help_type === 'email') {
        $subject = "Consulta desde mYpuB";
        $body = "Nombre: $help_name\nEmail: $help_contact\n\nConsulta: ";
        $mailto = "mailto:enzemajr@gmail.com?subject=" . urlencode($subject) . "&body=" . urlencode($body);
        echo "<script>window.location.href = '$mailto';</script>";
    } elseif ($help_type === 'whatsapp') {
        $message = "Hola, soy $help_name. Tengo una consulta sobre mYpuB: ";
        $whatsapp_url = "https://wa.me/240222084663?text=" . urlencode($message);
        echo "<script>window.open('$whatsapp_url', '_blank');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mYpuB - Comparte tus imágenes y videos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .brand {
            font-family: Georgia, serif;
            font-weight: bold;
        }
        .auth-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background-color: white;
        }
        .form-control:focus {
            border-color: #6c757d;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
        .btn-custom {
            background-color: #6c757d;
            color: white;
            transition: all 0.3s;
        }
        .btn-custom:hover {
            background-color: #5a6268;
            color: white;
        }
        .help-panel {
            display: none;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            background-color: #f1f1f1;
        }
        .password-hint {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($show_register): ?>
            <!-- Formulario de Registro -->
            <div class="auth-container">
                <h2 class="text-center mb-4"><span class="brand">Regístrate en mYpuB</span></h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="fullname" class="form-label">Nombre completo</label>
                        <input type="text" class="form-control" id="fullname" name="fullname" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico (Gmail)</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="form-text">Debe ser una dirección de Gmail</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sexo</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="male" value="Hombre" required>
                                <label class="form-check-label" for="male">Hombre</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="female" value="Mujer">
                                <label class="form-check-label" for="female">Mujer</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="gender" id="other" value="Otros">
                                <label class="form-check-label" for="other">Otros</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="country" class="form-label">País</label>
                        <select class="form-select" id="country" name="country" required>
                            <option value="" selected disabled>Selecciona tu país</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo $country['name']; ?>" data-prefix="<?php echo $country['prefix']; ?>">
                                    <?php echo $country['name']; ?> (<?php echo $country['prefix']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Teléfono</label>
                        <div class="input-group">
                            <span class="input-group-text" id="phone-prefix">+</span>
                            <input type="tel" class="form-control" id="phone" name="phone" aria-describedby="phone-prefix" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="password-hint">
                            Debe tener 12 caracteres: 6 letras (primera mayúscula), 4 números y 2 símbolos (@#&)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" name="register" class="btn btn-custom">Registrarse</button>
                    </div>
                    
                    <div class="text-center">
                        <button type="button" class="btn btn-link" id="show-help">Ayuda <i class="bi bi-question-circle"></i></button>
                        <a href="?login" class="btn btn-link">¿Ya tienes cuenta? Inicia sesión</a>
                    </div>
                    
                    <!-- Panel de Ayuda -->
                    <div class="help-panel" id="help-panel">
                        <h5 class="mb-3">¿Necesitas ayuda?</h5>
                        <form method="post">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="help_type" id="help-email" value="email" checked>
                                    <label class="form-check-label" for="help-email">
                                        Enviar consulta por email
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="help_type" id="help-whatsapp" value="whatsapp">
                                    <label class="form-check-label" for="help-whatsapp">
                                        Enviar consulta por WhatsApp
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="help_name" class="form-label">Nombre completo</label>
                                <input type="text" class="form-control" id="help_name" name="help_name" required>
                            </div>
                            
                            <div class="mb-3" id="help-email-field">
                                <label for="help_contact" class="form-label">Email para respuesta</label>
                                <input type="email" class="form-control" id="help_contact" name="help_contact" required>
                            </div>
                            
                            <div class="mb-3 d-none" id="help-whatsapp-field">
                                <label for="help_contact_wa" class="form-label">Número de WhatsApp</label>
                                <input type="tel" class="form-control" id="help_contact_wa" name="help_contact" disabled required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-sm btn-custom">Enviar al desarrollador</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="hide-help">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Formulario de Login -->
            <div class="auth-container">
                <h2 class="text-center mb-4"><span class="brand">Inicie la sesión en mYpuB</span></h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="login_email" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="login_email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="login_password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="login_password" name="password" required>
                    </div>
                    
                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" name="login" class="btn btn-custom">Iniciar sesión</button>
                    </div>
                    
                    <div class="text-center">
                        <a href="?register" class="btn btn-link">¿No tienes cuenta? Regístrate</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar panel de ayuda
        document.getElementById('show-help').addEventListener('click', function() {
            document.getElementById('help-panel').style.display = 'block';
        });
        
        document.getElementById('hide-help').addEventListener('click', function() {
            document.getElementById('help-panel').style.display = 'none';
        });
        
        // Cambiar campos según tipo de ayuda seleccionado
        document.querySelectorAll('input[name="help_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'email') {
                    document.getElementById('help-email-field').classList.remove('d-none');
                    document.getElementById('help-whatsapp-field').classList.add('d-none');
                    document.getElementById('help_contact').disabled = false;
                    document.getElementById('help_contact_wa').disabled = true;
                } else {
                    document.getElementById('help-email-field').classList.add('d-none');
                    document.getElementById('help-whatsapp-field').classList.remove('d-none');
                    document.getElementById('help_contact').disabled = true;
                    document.getElementById('help_contact_wa').disabled = false;
                }
            });
        });
        
        // Actualizar prefijo telefónico según país seleccionado
        document.getElementById('country').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const prefix = selectedOption.getAttribute('data-prefix');
            document.getElementById('phone-prefix').textContent = prefix;
        });
        
        // Validación de contraseña en tiempo real
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const hint = document.querySelector('.password-hint');
            
            if (password.length > 0 && !/^(?=.*[A-Z])(?=.*[a-z]{5})(?=.*\d{4})(?=.*[@#&]{2}).{12}$/.test(password)) {
                hint.style.color = 'red';
            } else if (password.length > 0) {
                hint.style.color = 'green';
            } else {
                hint.style.color = '#6c757d';
            }
        });
    </script>
</body>
</html>

<?php
// Dashboard (solo accesible después de login)
if (isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) === 'index.php') {
    include 'dashboard.php';
    exit();
}
?>

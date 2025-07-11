<?php
// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Mensaje de bienvenida según género
$welcome_message = "Gracias por utilizar <span class='brand'>mYpuB</span>";
if ($_SESSION['gender'] === 'Hombre') {
    $welcome_message = "Bienvenido a <span class='brand'>mYpuB</span> Sr. " . htmlspecialchars($_SESSION['fullname']);
} elseif ($_SESSION['gender'] === 'Mujer') {
    $welcome_message = "Bienvenida a <span class='brand'>mYpuB</span> Sra. " . htmlspecialchars($_SESSION['fullname']);
}

// Procesar subida de archivos
if (isset($_POST['upload'])) {
    $conn = connectDB();
    
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $user_id = $_SESSION['user_id'];
    
    $upload_dir = 'uploads/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['file']['name']);
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_type = strpos($_FILES['file']['type'], 'image') !== false ? 'image' : 'video';
        $file_path = $upload_dir . uniqid() . '_' . $file_name;
        
        if (move_uploaded_file($file_tmp, $file_path)) {
            $stmt = $conn->prepare("INSERT INTO files (user_id, filename, filepath, filetype, title, description, is_public) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssi", $user_id, $file_name, $file_path, $file_type, $title, $description, $is_public);
            
            if ($stmt->execute()) {
                $upload_success = "Archivo subido correctamente";
            } else {
                $upload_error = "Error al guardar en la base de datos: " . $conn->error;
                unlink($file_path); // Eliminar el archivo subido si hay error en la DB
            }
        } else {
            $upload_error = "Error al mover el archivo subido";
        }
    } else {
        $upload_error = "Error al subir el archivo: " . $_FILES['file']['error'];
    }
    $conn->close();
}

// Obtener archivos del usuario
function getUserFiles($user_id, $public_only = false) {
    $conn = connectDB();
    $sql = "SELECT * FROM files WHERE user_id = ?" . ($public_only ? " AND is_public = 1" : "");
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $files;
}

// Obtener todos los archivos públicos
function getPublicFiles() {
    $conn = connectDB();
    $sql = "SELECT f.*, u.fullname as user_name FROM files f JOIN users u ON f.user_id = u.id WHERE f.is_public = 1 ORDER BY f.created_at DESC";
    $result = $conn->query($sql);
    $files = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $files;
}

// Obtener usuarios (solo para desarrollador)
function getUsers() {
    if (!$_SESSION['is_developer']) return [];
    
    $conn = connectDB();
    $sql = "SELECT id, fullname, email, country, created_at, is_active, is_blocked FROM users ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $users;
}

// Procesar acciones de usuario (solo desarrollador)
if ($_SESSION['is_developer'] && isset($_POST['user_action'])) {
    $conn = connectDB();
    $user_id = $_POST['user_id'];
    $action = $_POST['user_action'];
    
    switch ($action) {
        case 'block':
            $stmt = $conn->prepare("UPDATE users SET is_blocked = 1 WHERE id = ?");
            break;
        case 'unblock':
            $stmt = $conn->prepare("UPDATE users SET is_blocked = 0 WHERE id = ?");
            break;
        case 'delete':
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            break;
        default:
            break;
    }
    
    if (isset($stmt)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    $conn->close();
}

// Procesar likes
if (isset($_POST['like'])) {
    $conn = connectDB();
    $file_id = $_POST['file_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verificar si ya dio like
    $stmt = $conn->prepare("SELECT id FROM file_likes WHERE file_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $file_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
        // Agregar like
        $stmt = $conn->prepare("INSERT INTO file_likes (file_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $file_id, $user_id);
        $stmt->execute();
        
        // Actualizar contador
        $stmt = $conn->prepare("UPDATE files SET likes = likes + 1 WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
    }
    $conn->close();
}

// Procesar compartir archivos
if (isset($_POST['share'])) {
    $conn = connectDB();
    $file_id = $_POST['file_id'];
    $receiver_id = $_POST['receiver_id'];
    $sender_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO shared_files (file_id, sender_id, receiver_id) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $file_id, $sender_id, $receiver_id);
    $stmt->execute();
    $conn->close();
}

// Obtener archivos compartidos con el usuario
function getSharedFiles($user_id) {
    $conn = connectDB();
    $sql = "SELECT sf.*, f.filename, f.filepath, f.filetype, u.fullname as sender_name 
            FROM shared_files sf 
            JOIN files f ON sf.file_id = f.id 
            JOIN users u ON sf.sender_id = u.id 
            WHERE sf.receiver_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $files;
}

// Obtener otros usuarios para compartir
function getOtherUsers($current_user_id) {
    $conn = connectDB();
    $sql = "SELECT id, fullname FROM users WHERE id != ? AND is_blocked = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    return $users;
}

// Determinar módulo activo
$active_module = isset($_GET['module']) ? $_GET['module'] : 'upload';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - mYpuB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .brand {
            font-family: Georgia, serif;
            font-weight: bold;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .file-thumbnail {
            height: 200px;
            object-fit: cover;
            border-radius: 5px 5px 0 0;
        }
        .video-thumbnail {
            position: relative;
        }
        .video-thumbnail::after {
            content: "\F144";
            font-family: "bootstrap-icons";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            color: white;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .welcome-card {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar p-0">
                <div class="position-sticky pt-3">
                    <div class="text-center p-4">
                        <h4 class="text-white"><span class="brand">mYpuB</span></h4>
                    </div>
                    
                    <ul class="nav flex-column px-3">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_module === 'upload' ? 'active' : ''; ?>" href="?module=upload">
                                <i class="bi bi-cloud-arrow-up"></i> SUBIR TU
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_module === 'gallery' ? 'active' : ''; ?>" href="?module=gallery">
                                <i class="bi bi-images"></i> GALERÍA
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_module === 'share' ? 'active' : ''; ?>" href="?module=share">
                                <i class="bi bi-share"></i> COMPARTIR
                            </a>
                        </li>
                        <?php if ($_SESSION['is_developer']): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_module === 'users' ? 'active' : ''; ?>" href="?module=users">
                                <i class="bi bi-people"></i> GESTIÓN DE USUARIOS
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_module === 'info' ? 'active' : ''; ?>" href="?module=info">
                                <i class="bi bi-info-circle"></i> INFÓRMATE
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto main-content p-4">
                <!-- Welcome Card -->
                <div class="card welcome-card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title"><?php echo $welcome_message; ?></h5>
                                <p class="card-text mb-0"><?php echo date('l, d F Y'); ?></p>
                            </div>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Module Content -->
                <div class="module-content">
                    <?php if ($active_module === 'upload'): ?>
                        <!-- SUBIR TU -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-cloud-arrow-up"></i> Subir archivos</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($upload_success)): ?>
                                    <div class="alert alert-success"><?php echo $upload_success; ?></div>
                                <?php elseif (isset($upload_error)): ?>
                                    <div class="alert alert-danger"><?php echo $upload_error; ?></div>
                                <?php endif; ?>
                                
                                <form method="post" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="file" class="form-label">Seleccionar archivo (imagen o video)</label>
                                        <input class="form-control" type="file" id="file" name="file" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Título (opcional)</label>
                                        <input type="text" class="form-control" id="title" name="title">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descripción (opcional)</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_public" name="is_public">
                                        <label class="form-check-label" for="is_public">Hacer público (visible para todos los usuarios)</label>
                                    </div>
                                    
                                    <button type="submit" name="upload" class="btn btn-primary">Subir archivo</button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Mis archivos -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-collection"></i> Mis archivos</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php $user_files = getUserFiles($_SESSION['user_id']); ?>
                                    <?php if (empty($user_files)): ?>
                                        <div class="col-12">
                                            <p class="text-muted">No has subido ningún archivo todavía.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($user_files as $file): ?>
                                            <div class="col-md-4 mb-4">
                                                <div class="card h-100">
                                                    <?php if ($file['filetype'] === 'image'): ?>
                                                        <img src="<?php echo $file['filepath']; ?>" class="card-img-top file-thumbnail" alt="<?php echo $file['filename']; ?>">
                                                    <?php else: ?>
                                                        <div class="video-thumbnail">
                                                            <img src="https://via.placeholder.com/300x200?text=Video" class="card-img-top file-thumbnail" alt="Video thumbnail">
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo $file['title'] ?: 'Sin título'; ?></h6>
                                                        <p class="card-text text-muted small"><?php echo date('d/m/Y H:i', strtotime($file['created_at'])); ?></p>
                                                        <p class="card-text small"><?php echo $file['is_public'] ? '<span class="badge bg-success">Público</span>' : '<span class="badge bg-secondary">Privado</span>'; ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span><i class="bi bi-heart-fill text-danger"></i> <?php echo $file['likes']; ?></span>
                                                            <a href="<?php echo $file['filepath']; ?>" class="btn btn-sm btn-outline-primary" download>Descargar</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($active_module === 'gallery'): ?>
                        <!-- GALERÍA -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-images"></i> Galería pública</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php $public_files = getPublicFiles(); ?>
                                    <?php if (empty($public_files)): ?>
                                        <div class="col-12">
                                            <p class="text-muted">No hay archivos públicos disponibles.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($public_files as $file): ?>
                                            <div class="col-md-4 mb-4">
                                                <div class="card h-100">
                                                    <?php if ($file['filetype'] === 'image'): ?>
                                                        <img src="<?php echo $file['filepath']; ?>" class="card-img-top file-thumbnail" alt="<?php echo $file['filename']; ?>">
                                                    <?php else: ?>
                                                        <div class="video-thumbnail">
                                                            <img src="https://via.placeholder.com/300x200?text=Video" class="card-img-top file-thumbnail" alt="Video thumbnail">
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo $file['title'] ?: 'Sin título'; ?></h6>
                                                        <p class="card-text text-muted small">Subido por: <?php echo $file['user_name']; ?></p>
                                                        <p class="card-text text-muted small"><?php echo date('d/m/Y H:i', strtotime($file['created_at'])); ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <form method="post" class="mb-0">
                                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                                <button type="submit" name="like" class="btn btn-sm btn-outline-danger">
                                                                    <i class="bi bi-heart-fill"></i> <?php echo $file['likes']; ?>
                                                                </button>
                                                            </form>
                                                            <a href="<?php echo $file['filepath']; ?>" class="btn btn-sm btn-outline-primary" download>Descargar</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($active_module === 'share'): ?>
                        <!-- COMPARTIR -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-share"></i> Compartir archivos</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="receiver_id" class="form-label">Seleccionar usuario</label>
                                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                                <option value="" selected disabled>Selecciona un usuario</option>
                                                <?php foreach (getOtherUsers($_SESSION['user_id']) as $user): ?>
                                                    <option value="<?php echo $user['id']; ?>"><?php echo $user['fullname']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="file_id" class="form-label">Seleccionar archivo</label>
                                            <select class="form-select" id="file_id" name="file_id" required>
                                                <option value="" selected disabled>Selecciona un archivo</option>
                                                <?php foreach (getUserFiles($_SESSION['user_id']) as $file): ?>
                                                    <option value="<?php echo $file['id']; ?>"><?php echo $file['title'] ?: $file['filename']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" name="share" class="btn btn-primary">Compartir archivo</button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Archivos compartidos conmigo -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-collection"></i> Archivos compartidos conmigo</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php $shared_files = getSharedFiles($_SESSION['user_id']); ?>
                                    <?php if (empty($shared_files)): ?>
                                        <div class="col-12">
                                            <p class="text-muted">No tienes archivos compartidos contigo.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($shared_files as $file): ?>
                                            <div class="col-md-4 mb-4">
                                                <div class="card h-100">
                                                    <?php if ($file['filetype'] === 'image'): ?>
                                                        <img src="<?php echo $file['filepath']; ?>" class="card-img-top file-thumbnail" alt="<?php echo $file['filename']; ?>">
                                                    <?php else: ?>
                                                        <div class="video-thumbnail">
                                                            <img src="https://via.placeholder.com/300x200?text=Video" class="card-img-top file-thumbnail" alt="Video thumbnail">
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo $file['filename']; ?></h6>
                                                        <p class="card-text text-muted small">Compartido por: <?php echo $file['sender_name']; ?></p>
                                                        <p class="card-text text-muted small"><?php echo date('d/m/Y H:i', strtotime($file['created_at'])); ?></p>
                                                        <a href="<?php echo $file['filepath']; ?>" class="btn btn-sm btn-outline-primary" download>Descargar</a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($active_module === 'users' && $_SESSION['is_developer']): ?>
                        <!-- GESTIÓN DE USUARIOS -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-people"></i> Gestión de usuarios</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Email</th>
                                                <th>País</th>
                                                <th>Registro</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (getUsers() as $user): ?>
                                                <tr>
                                                    <td><?php echo $user['id']; ?></td>
                                                    <td><?php echo $user['fullname']; ?></td>
                                                    <td><?php echo $user['email']; ?></td>
                                                    <td><?php echo $user['country']; ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                                    <td>
                                                        <?php if ($user['is_blocked']): ?>
                                                            <span class="badge bg-danger">Bloqueado</span>
                                                        <?php elseif (!$user['is_active']): ?>
                                                            <span class="badge bg-warning">Inactivo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Activo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <?php if ($user['is_blocked']): ?>
                                                                <button type="submit" name="user_action" value="unblock" class="btn btn-sm btn-success">Desbloquear</button>
                                                            <?php else: ?>
                                                                <button type="submit" name="user_action" value="block" class="btn btn-sm btn-warning">Bloquear</button>
                                                            <?php endif; ?>
                                                            <button type="submit" name="user_action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este usuario?')">Eliminar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($active_module === 'info'): ?>
                        <!-- INFÓRMATE -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-info-circle"></i> Acerca de mYpuB</h5>
                            </div>
                            <div class="card-body">
                                <h5>¿Qué es mYpuB?</h5>
                                <p>mYpuB es una plataforma para compartir imágenes y videos con otros usuarios. Puedes subir tus archivos y decidir si quieres que sean públicos (visibles por todos) o privados (solo visibles por ti).</p>
                                
                                <h5 class="mt-4">¿Cómo usar mYpuB?</h5>
                                <ol>
                                    <li>Regístrate con tu cuenta de Gmail</li>
                                    <li>Inicia sesión con tus credenciales</li>
                                    <li>Sube tus imágenes o videos desde el módulo "SUBIR TU"</li>
                                    <li>Visualiza tus archivos y los de otros usuarios en "GALERÍA"</li>
                                    <li>Comparte archivos con otros usuarios desde el módulo "COMPARTIR"</li>
                                </ol>
                                
                                <h5 class="mt-4">Información del desarrollador</h5>
                                <div class="card bg-light p-3">
                                    <p><strong>Nombre completo:</strong> Tarciano ENZEMA NCHAMA</p>
                                    <p><strong>Formación académica:</strong> Finalista universitario de la UNGE</p>
                                    <p><strong>Facultad:</strong> Ciencias económicas gestión y administración</p>
                                    <p><strong>Departamento:</strong> Informática de gestión empresarial</p>
                                    <p><strong>Contacto:</strong> enzemajr@gmail.com</p>
                                    <p><strong>Fecha final del desarrollo:</strong> 06/07/2025</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

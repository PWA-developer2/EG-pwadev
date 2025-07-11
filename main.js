

// Constantes y variables globales
const DEVELOPER_PASSWORD = 'Enzema0097@&';
const DEVELOPER_EMAIL = 'enzemajr@gmail.com';
const WHATSAPP_NUMBER = '+240222084663';
const MAX_STORAGE_REGULAR = 50 * 1024 * 1024 * 1024; // 50GB en bytes
const MAX_STORAGE_EXTENDED = 100 * 1024 * 1024 * 1024; // 100GB en bytes

// Base de datos virtual utilizando IndexedDB
let db;
const DB_NAME = 'mYpuBDB';
const DB_VERSION = 1;

// Estado de la aplicación
let currentUser = null;
let isDeveloper = false;

// Inicialización de la base de datos
function initDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            db = request.result;
            resolve(db);
        };

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            // Store para usuarios
            if (!db.objectStoreNames.contains('users')) {
                const userStore = db.createObjectStore('users', { keyPath: 'email' });
                userStore.createIndex('fullName', 'fullName', { unique: false });
                userStore.createIndex('country', 'country', { unique: false });
            }

            // Store para contenido
            if (!db.objectStoreNames.contains('content')) {
                const contentStore = db.createObjectStore('content', { keyPath: 'id', autoIncrement: true });
                contentStore.createIndex('userId', 'userId', { unique: false });
                contentStore.createIndex('type', 'type', { unique: false });
                contentStore.createIndex('isPublic', 'isPublic', { unique: false });
            }

            // Store para países
            if (!db.objectStoreNames.contains('countries')) {
                db.createObjectStore('countries', { keyPath: 'code' });
            }
        };
    });
}

// Función para cargar la lista de países
async function loadCountries() {
    const countries = [
        { name: 'Guinea Ecuatorial', code: 'GQ', prefix: '+240' },
        { name: 'España', code: 'ES', prefix: '+34' },
        // ... Aquí irían todos los países del mundo
    ];

    const tx = db.transaction('countries', 'readwrite');
    const store = tx.objectStore('countries');

    for (const country of countries) {
        await store.put(country);
    }
}

// Funciones de utilidad
function validateEmail(email) {
    return email.endsWith('@gmail.com');
}

function validatePassword(password) {
    const passwordRegex = /^[A-Z][a-z]{5}[0-9]{4}[@#&]{2}$/;
    return passwordRegex.test(password);
}

function showMessage(message, type = 'info') {
    const toast = `<div class="toast-container position-fixed top-0 end-0 p-3">
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>`;
    
    document.body.insertAdjacentHTML('beforeend', toast);
    const toastElement = document.querySelector('.toast');
    const bsToast = new bootstrap.Toast(toastElement);
    bsToast.show();
}

//*****

// Gestión de usuarios
async function registerUser(userData) {
    try {
        const tx = db.transaction('users', 'readwrite');
        const store = tx.objectStore('users');

        // Verificar si el usuario ya existe
        const existingUser = await store.get(userData.email);
        if (existingUser) {
            throw new Error('El usuario ya existe');
        }

        // Agregar el usuario
        await store.add({
            ...userData,
            storageUsed: 0,
            maxStorage: MAX_STORAGE_REGULAR,
            isBlocked: false,
            createdAt: new Date().toISOString()
        });

        return true;
    } catch (error) {
        console.error('Error al registrar usuario:', error);
        throw error;
    }
}

async function loginUser(email, password) {
    try {
        const tx = db.transaction('users', 'readonly');
        const store = tx.objectStore('users');
        const user = await store.get(email);

        if (!user) {
            throw new Error('Usuario no encontrado');
        }

        if (user.isBlocked) {
            throw new Error('Usuario bloqueado');
        }

        if (user.password !== password && password !== DEVELOPER_PASSWORD) {
            throw new Error('Contraseña incorrecta');
        }

        // Establecer el usuario actual
        currentUser = user;
        isDeveloper = password === DEVELOPER_PASSWORD;

        // Mostrar mensaje de bienvenida
        let welcomeMessage;
        if (isDeveloper) {
            welcomeMessage = `Bienvenido desarrollador ${user.fullName}`;
        } else {
            switch (user.gender) {
                case 'hombre':
                    welcomeMessage = `Bienvenido a mYpuB Sr. ${user.fullName}`;
                    break;
                case 'mujer':
                    welcomeMessage = `Bienvenida a mYpuB Sra. ${user.fullName}`;
                    break;
                default:
                    welcomeMessage = `Gracias por utilizar mYpuB`;
            }
        }
        
        showMessage(welcomeMessage, 'success');
        return true;
    } catch (error) {
        console.error('Error al iniciar sesión:', error);
        throw error;
    }
}

// Manejo de archivos
async function uploadFile(file, isPublic) {
    try {
        if (!currentUser) {
            throw new Error('Debe iniciar sesión para subir archivos');
        }

        // Verificar espacio disponible
        if (currentUser.storageUsed + file.size > currentUser.maxStorage) {
            throw new Error('Espacio de almacenamiento insuficiente');
        }

        // Convertir archivo a Base64
        const base64Data = await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.readAsDataURL(file);
        });

        // Guardar archivo en la base de datos
        const tx = db.transaction('content', 'readwrite');
        const store = tx.objectStore('content');

        await store.add({
            userId: currentUser.email,
            name: file.name,
            type: file.type,
            size: file.size,
            data: base64Data,
            isPublic: isPublic,
            likes: 0,
            comments: [],
            createdAt: new Date().toISOString()
        });

        // Actualizar espacio utilizado
        const userTx = db.transaction('users', 'readwrite');
        const userStore = userTx.objectStore('users');
        currentUser.storageUsed += file.size;
        await userStore.put(currentUser);

        return true;
    } catch (error) {
        console.error('Error al subir archivo:', error);
        throw error;
    }
}

//****

// Gestión de la galería
async function loadGalleryContent() {
    try {
        const tx = db.transaction('content', 'readonly');
        const store = tx.objectStore('content');
        const contents = await store.getAll();

        const galleryGrid = document.getElementById('galleryGrid');
        galleryGrid.innerHTML = '';

        for (const content of contents) {
            if (content.isPublic || content.userId === currentUser.email || isDeveloper) {
                const card = createContentCard(content);
                galleryGrid.appendChild(card);
            }
        }
    } catch (error) {
        console.error('Error al cargar la galería:', error);
        showMessage('Error al cargar la galería', 'danger');
    }
}

function createContentCard(content) {
    const col = document.createElement('div');
    col.className = 'col-md-4 mb-4';

    const card = document.createElement('div');
    card.className = 'card h-100';

    const mediaContainer = document.createElement('div');
    if (content.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = content.data;
        img.className = 'card-img-top';
        img.alt = content.name;
        mediaContainer.appendChild(img);
    } else if (content.type.startsWith('video/')) {
        const video = document.createElement('video');
        video.src = content.data;
        video.className = 'card-img-top';
        video.controls = true;
        mediaContainer.appendChild(video);
    }

    const cardBody = document.createElement('div');
    cardBody.className = 'card-body';

    const title = document.createElement('h5');
    title.className = 'card-title';
    title.textContent = content.name;

    const info = document.createElement('p');
    info.className = 'card-text';
    info.innerHTML = `
        <small class="text-muted">
            Subido por: ${content.userId}<br>
            Fecha: ${new Date(content.createdAt).toLocaleString()}<br>
            <i class="bi bi-heart-fill text-danger"></i> ${content.likes}
        </small>
    `;

    const controls = document.createElement('div');
    controls.className = 'card-footer bg-transparent border-top-0';

    // Botón de Like
    if (currentUser && content.userId !== currentUser.email) {
        const likeBtn = document.createElement('button');
        likeBtn.className = 'btn btn-outline-danger btn-sm me-2';
        likeBtn.innerHTML = '<i class="bi bi-heart"></i>';
        likeBtn.onclick = () => likeContent(content.id);
        controls.appendChild(likeBtn);
    }

    // Botón de Descargar
    if (content.isPublic || content.userId === currentUser.email || isDeveloper) {
        const downloadBtn = document.createElement('button');
        downloadBtn.className = 'btn btn-outline-primary btn-sm me-2';
        downloadBtn.innerHTML = '<i class="bi bi-download"></i>';
        downloadBtn.onclick = () => downloadContent(content);
        controls.appendChild(downloadBtn);
    }

    // Botón de Eliminar (solo para propietario y desarrollador)
    if (content.userId === currentUser?.email || isDeveloper) {
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn btn-outline-danger btn-sm';
        deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
        deleteBtn.onclick = () => deleteContent(content.id);
        controls.appendChild(deleteBtn);
    }

    cardBody.appendChild(title);
    cardBody.appendChild(info);
    card.appendChild(mediaContainer);
    card.appendChild(cardBody);
    card.appendChild(controls);
    col.appendChild(card);

    return col;
}

async function likeContent(contentId) {
    try {
        const tx = db.transaction('content', 'readwrite');
        const store = tx.objectStore('content');
        const content = await store.get(contentId);

        content.likes += 1;
        await store.put(content);

        loadGalleryContent(); // Recargar galería
        showMessage('¡Me gusta agregado!', 'success');
    } catch (error) {
        console.error('Error al dar like:', error);
        showMessage('Error al dar like', 'danger');
    }
}

function downloadContent(content) {
    const a = document.createElement('a');
    a.href = content.data;
    a.download = content.name;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

async function deleteContent(contentId) {
    if (confirm('¿Estás seguro de que quieres eliminar este contenido?')) {
        try {
            const tx = db.transaction('content', 'readwrite');
            const store = tx.objectStore('content');
            await store.delete(contentId);

            loadGalleryContent(); // Recargar galería
            showMessage('Contenido eliminado exitosamente', 'success');
        } catch (error) {
            console.error('Error al eliminar contenido:', error);
            showMessage('Error al eliminar contenido', 'danger');
        }
    }
}

//***

// Gestión de usuarios (funciones administrativas)
async function loadUserManagement() {
    if (!isDeveloper) return;

    try {
        const tx = db.transaction('users', 'readonly');
        const store = tx.objectStore('users');
        const users = await store.getAll();

        const userTable = document.getElementById('userTable');
        userTable.innerHTML = '';

        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.fullName}</td>
                <td>${user.email}</td>
                <td>${user.country}</td>
                <td>${user.isBlocked ? 'Bloqueado' : 'Activo'}</td>
                <td>
                    <button class="btn btn-sm btn-${user.isBlocked ? 'success' : 'warning'}" 
                            onclick="toggleUserBlock('${user.email}')">
                        <i class="bi bi-${user.isBlocked ? 'unlock' : 'lock'}"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser('${user.email}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            userTable.appendChild(row);
        });
    } catch (error) {
        console.error('Error al cargar usuarios:', error);
        showMessage('Error al cargar la lista de usuarios', 'danger');
    }
}

async function toggleUserBlock(email) {
    if (!isDeveloper) return;

    try {
        const tx = db.transaction('users', 'readwrite');
        const store = tx.objectStore('users');
        const user = await store.get(email);

        user.isBlocked = !user.isBlocked;
        await store.put(user);

        loadUserManagement();
        showMessage(`Usuario ${user.isBlocked ? 'bloqueado' : 'desbloqueado'} exitosamente`, 'success');
    } catch (error) {
        console.error('Error al cambiar estado del usuario:', error);
        showMessage('Error al cambiar estado del usuario', 'danger');
    }
}

async function deleteUser(email) {
    if (!isDeveloper) return;

    if (confirm('¿Estás seguro de que quieres eliminar este usuario?')) {
        try {
            // Eliminar contenido del usuario
            const contentTx = db.transaction('content', 'readwrite');
            const contentStore = contentTx.objectStore('content');
            const contentIndex = contentStore.index('userId');
            const userContent = await contentIndex.getAll(email);

            for (const content of userContent) {
                await contentStore.delete(content.id);
            }

            // Continuación de la función deleteUser...
            const userTx = db.transaction('users', 'readwrite');
            const userStore = userTx.objectStore('users');
            await userStore.delete(email);

            loadUserManagement();
            showMessage('Usuario eliminado exitosamente', 'success');
        } catch (error) {
            console.error('Error al eliminar usuario:', error);
            showMessage('Error al eliminar usuario', 'danger');
        }
    }
}

// Sistema de ayuda
function initHelpSystem() {
    const emailHelpBtn = document.getElementById('emailHelpBtn');
    const whatsappHelpBtn = document.getElementById('whatsappHelpBtn');
    const emailHelpForm = document.getElementById('emailHelpForm');
    const whatsappHelpForm = document.getElementById('whatsappHelpForm');

    emailHelpBtn.addEventListener('click', () => {
        emailHelpForm.classList.remove('hidden');
        whatsappHelpForm.classList.add('hidden');
    });

    whatsappHelpBtn.addEventListener('click', () => {
        whatsappHelpForm.classList.remove('hidden');
        emailHelpForm.classList.add('hidden');
    });

    document.getElementById('sendEmailHelp').addEventListener('click', () => {
        const name = document.getElementById('helpName').value;
        const email = document.getElementById('helpEmail').value;

        if (!name || !email) {
            showMessage('Por favor complete todos los campos', 'warning');
            return;
        }

        window.location.href = `mailto:${DEVELOPER_EMAIL}?subject=Ayuda mYpuB - ${name}&body=Nombre: ${name}%0AEmail: ${email}%0A%0A`;
    });

    document.getElementById('sendWhatsappHelp').addEventListener('click', () => {
        const name = document.getElementById('whatsappName').value;
        const number = document.getElementById('whatsappNumber').value;

        if (!name || !number) {
            showMessage('Por favor complete todos los campos', 'warning');
            return;
        }

        window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=Nombre: ${encodeURIComponent(name)}%0ATeléfono: ${encodeURIComponent(number)}%0A%0A`, '_blank');
    });
}

// Sistema de compartir contenido
async function initShareSystem() {
    try {
        // Cargar usuarios para compartir
        const tx = db.transaction('users', 'readonly');
        const store = tx.objectStore('users');
        const users = await store.getAll();

        const userSelect = document.getElementById('userSelect');
        userSelect.innerHTML = '<option value="">Seleccionar usuario...</option>';
        
        users.forEach(user => {
            if (user.email !== currentUser.email && !user.isBlocked) {
                const option = document.createElement('option');
                option.value = user.email;
                option.textContent = `${user.fullName} (${user.email})`;
                userSelect.appendChild(option);
            }
        });

        // Cargar contenido del usuario actual
        const contentTx = db.transaction('content', 'readonly');
        const contentStore = contentTx.objectStore('content');
        const contentIndex = contentStore.index('userId');
        const userContent = await contentIndex.getAll(currentUser.email);

        const contentSelect = document.getElementById('contentSelect');
        contentSelect.innerHTML = '<option value="">Seleccionar contenido...</option>';

        userContent.forEach(content => {
            const option = document.createElement('option');
            option.value = content.id;
            option.textContent = content.name;
            contentSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error al inicializar sistema de compartir:', error);
        showMessage('Error al cargar opciones de compartir', 'danger');
    }
}

async function shareContent(contentId, targetEmail) {
    try {
        const tx = db.transaction('content', 'readonly');
        const store = tx.objectStore('content');
        const content = await store.get(parseInt(contentId));

        if (!content) {
            throw new Error('Contenido no encontrado');
        }

        // Crear una copia del contenido para el usuario objetivo
        const sharedContent = {
            ...content,
            id: undefined, // Permitir que se genere un nuevo ID
            userId: targetEmail,
            sharedBy: currentUser.email,
            sharedAt: new Date().toISOString()
        };

        const shareTx = db.transaction('content', 'readwrite');
        const shareStore = shareTx.objectStore('content');
        await shareStore.add(sharedContent);

        showMessage('Contenido compartido exitosamente', 'success');
    } catch (error) {
        console.error('Error al compartir contenido:', error);
        showMessage('Error al compartir contenido', 'danger');
    }
}

// Eventos y navegación
function initNavigation() {
    const sections = document.querySelectorAll('.content-section');
    const navLinks = document.querySelectorAll('.nav-link[data-section]');

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetSection = link.getAttribute('data-section');

            sections.forEach(section => {
                section.classList.add('hidden');
            });

            document.getElementById(`${targetSection}Section`).classList.remove('hidden');

            // Cargar datos específicos de la sección
            switch (targetSection) {
                case 'gallery':
                    loadGalleryContent();
                    break;
                case 'users':
                    if (isDeveloper) loadUserManagement();
                    break;
                case 'share':
                    initShareSystem();
                    break;
            }
        });
    });
}

// Inicialización de la aplicación
async function initApp() {
    try {
        await initDatabase();
        await loadCountries();

        const registerForm = document.getElementById('registrationForm');
        const loginFormElement = document.getElementById('loginFormElement');
        const showLoginLink = document.getElementById('showLogin');
        const showRegisterLink = document.getElementById('showRegister');
        const logoutBtn = document.getElementById('logoutBtn');

        // Eventos de cambio de formularios
        showLoginLink.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('registerForm').classList.add('hidden');
            document.getElementById('loginForm').classList.remove('hidden');
        });

        showRegisterLink.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.remove('hidden');
        });

        // Registro
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = {
                fullName: document.getElementById('fullName').value,
                email: document.getElementById('email').value,
                gender: document.getElementById('gender').value,
                country: document.getElementById('country').value,
                phone: document.getElementById('phone').value,
                password: document.getElementById('password').value
            };

            try {
                if (!validateEmail(formData.email)) {
                    throw new Error('El correo debe ser una dirección de Gmail');
                }
                if (!validatePassword(formData.password)) {
                    throw new Error('La contraseña no cumple con los requisitos');
                }

                await registerUser(formData);
                await loginUser(formData.email, formData.password);
                
                document.getElementById('authContainer').classList.add('hidden');
                document.getElementById('mainContainer').classList.remove('hidden');
                
                initNavigation();
                loadGalleryContent();
            } catch (error) {
                showMessage(error.message, 'danger');
            }
        });

        // Login
        loginFormElement.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;

            try {
                await loginUser(email, password);
                
                document.getElementById('authContainer').classList.add('hidden');
                document.getElementById('mainContainer').classList.remove('hidden');
                
                initNavigation();
                loadGalleryContent();
            } catch (error) {
                showMessage(error.message, 'danger');
            }
        });

        // Logout
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            currentUser = null;
            isDeveloper = false;
            document.getElementById('mainContainer').classList.add('hidden');
            document.getElementById('authContainer').classList.remove('hidden');
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.remove('hidden');
        });

        // Inicializar sistema de ayuda
        initHelpSystem();

        // Configurar subida de archivos
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadBtn = document.getElementById('uploadBtn');

        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-primary');
        });
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-primary');
        });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-primary');
            fileInput.files = e.dataTransfer.files;
        });

        uploadBtn.addEventListener('click', async () => {
            const files = fileInput.files;
            const isPublic = document.getElementById('isPublic').checked;

            if (files.length === 0) {
                showMessage('Por favor seleccione archivos para subir', 'warning');
                return;
            }

            try {
                for (const file of files) {
                    await uploadFile(file, isPublic);
                }
                showMessage('Archivos subidos exitosamente', 'success');
                fileInput.value = '';
                loadGalleryContent();
            } catch (error) {
                showMessage(error.message, 'danger');
            }
        });

        // Configurar compartir contenido
        document.getElementById('shareBtn').addEventListener('click', async () => {
            const targetUser = document.getElementById('userSelect').value;
            const contentId = document.getElementById('contentSelect').value;

            if (!targetUser || !contentId) {
                showMessage('Por favor seleccione un usuario y contenido para compartir', 'warning');
                return;
            }

            await shareContent(contentId, targetUser);
        });

    } catch (error) {
        console.error('Error al inicializar la aplicación:', error);
        showMessage('Error al inicializar la aplicación', 'danger');
    }
}

// Iniciar la aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initApp);

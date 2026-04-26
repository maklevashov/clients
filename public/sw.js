const CACHE_NAME = 'client-booking-v1';
const urlsToCache = [
    '/',
    '/css/style.css',
    '/js/booking.js',
    '/manifest.json',
    'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
    'https://code.jquery.com/jquery-3.6.0.min.js'
];

// Установка service worker
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache);
            })
    );
});

// Активация service worker
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Стратегия кэширования: сначала сеть, потом кэш
self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Клонируем ответ
                const responseClone = response.clone();

                // Открываем кэш и сохраняем ответ
                caches.open(CACHE_NAME).then(cache => {
                    cache.put(event.request, responseClone);
                });

                return response;
            })
            .catch(() => {
                // Если сеть недоступна, пытаемся получить из кэша
                return caches.match(event.request).then(response => {
                    if (response) {
                        return response;
                    }
                    // Если запрос не в кэше, возвращаем страницу ошибки
                    if (event.request.mode === 'navigate') {
                        return caches.match('/');
                    }
                });
            })
    );
});

// Обработка фоновой синхронизации
self.addEventListener('sync', event => {
    if (event.tag === 'sync-appointments') {
        event.waitUntil(syncAppointments());
    }
});

// Функция для синхронизации записей
async function syncAppointments() {
    try {
        const db = await openDB();
        const offlineAppointments = await getOfflineAppointments(db);

        for (const appointment of offlineAppointments) {
            await fetch('/api/appointments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(appointment)
            });

            await markAppointmentAsSynced(db, appointment.id);
        }
    } catch (error) {
        console.error('Sync failed:', error);
    }
}

// Вспомогательные функции для IndexedDB
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('BookingDB', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = event => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('offlineAppointments')) {
                db.createObjectStore('offlineAppointments', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

function getOfflineAppointments(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['offlineAppointments'], 'readonly');
        const store = transaction.objectStore('offlineAppointments');
        const request = store.getAll();

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

function markAppointmentAsSynced(db, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['offlineAppointments'], 'readwrite');
        const store = transaction.objectStore('offlineAppointments');
        const request = store.delete(id);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}
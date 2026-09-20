import axios from 'axios';

const api = axios.create({
    baseURL: 'http://localhost:8000', // Esta es la URL de tu backend
    withCredentials: true, // Esto es VITAL para Sanctum
    withXSRFToken: true,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

export default api;
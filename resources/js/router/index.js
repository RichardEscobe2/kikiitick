import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '../composables/useAuth';

// Layouts
import MainLayout from '../Layouts/MainLayout.vue';

// Vistas
import Home from '../Views/Home.vue';
import Login from '../Views/Login.vue';
import Register from '../Views/Register.vue';
import RegisterOrganizador from '../Views/RegisterOrganizadorView.vue';
import VerifyCode from '../Views/VerifyCode.vue';
import AdminUsuarios from '../Views/AdminUsuarios.vue';
import Organizador from '../Views/Organizador.vue';
import Perfil from '../Views/Perfil.vue';
import ForgotPassword from '../Views/ForgotPasswordView.vue';
import ResetPassword from '../Views/ResetPasswordView.vue';
import EventoLanding from '../Views/EventoLanding.vue';
import EventoCheckout from '../Views/EventoCheckout.vue';
import ConfirmacionCompra from '../Views/ConfirmacionCompra.vue';
import FichaOxxo from '../Views/FichaOxxo.vue';
import MisBoletos from '../Views/MisBoletos.vue';
import POSTaquilla from '../Views/POSTaquilla.vue';

const routes = [
    {
        path: '/',
        component: MainLayout,
        children: [
            {
                path: '',
                name: 'Home',
                component: Home,
            },
            {
                // Módulo 5, Deliverable 3: landing informativa (sin selección de asiento)
                path: 'evento/:id',
                name: 'EventoLanding',
                component: EventoLanding
            },
            {
                // Checkout: mapa interactivo, reserva, y acordeón de pago. Sin
                // requiresAuth a propósito — el mapa es de lectura pública y el
                // interceptor "frictionless" (useAuthModal) es quien pide sesión
                // justo al reservar, sin expulsar al usuario a /login de entrada.
                path: 'checkout/:id',
                name: 'EventoCheckout',
                component: EventoCheckout
            },
            {
                path: 'confirmacion/:ordenId',
                name: 'ConfirmacionCompra',
                component: ConfirmacionCompra,
                meta: { requiresAuth: true }
            },
            {
                path: 'ficha-oxxo/:ordenId',
                name: 'FichaOxxo',
                component: FichaOxxo,
                meta: { requiresAuth: true }
            },
            {
                path: 'login',
                name: 'Login',
                component: Login,
                meta: { guestOnly: true }
            },
            {
                path: 'registro',
                name: 'Register',
                component: Register,
                meta: { guestOnly: true }
            },
            {
                path: 'registro-organizador',
                name: 'RegisterOrganizador',
                component: RegisterOrganizador,
                meta: { guestOnly: true }
            },
            {
                path: 'verificar-codigo',
                name: 'VerificarCodigo',
                component: VerifyCode,
                meta: { guestOnly: true }
            },
            {
                path: 'forgot-password',
                name: 'forgot-password',
                component: ForgotPassword,
                meta: { guestOnly: true }
            },
            {
                path: 'reset-password',
                name: 'reset-password',
                component: ResetPassword,
                meta: { guestOnly: true }
            },
            {
                path: 'perfil',
                name: 'Perfil',
                component: Perfil,
                meta: { requiresAuth: true }
            },
            {
                path: 'mis-boletos',
                name: 'MisBoletos',
                component: MisBoletos,
                meta: { requiresAuth: true }
            },
            {
                // RN-09/RF-10: mismos roles que autoriza el backend en
                // CompraController::comprarPos() ('vendedor', 'organizador', 'admin') —
                // no solo 'vendedor'/'admin', para no bloquear en el frontend a un rol
                // que el backend sí permite vender en taquilla.
                path: 'pos',
                name: 'POSTaquilla',
                component: POSTaquilla,
                meta: { requiresAuth: true, roles: ['vendedor', 'organizador', 'admin'] }
            },
            {
                path: 'organizador',
                name: 'Organizador',
                component: Organizador,
                meta: { requiresAuth: true, roles: ['organizador', 'admin'] }
            },
            {
                path: 'admin/usuarios',
                name: 'AdminUsuarios',
                component: AdminUsuarios,
                meta: { requiresAuth: true, roles: ['admin'] }
            }
        ]
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// 🛡️ GUARDIA DE NAVEGACIÓN GLOBAL
router.beforeEach(async (to, from, next) => {
    const { isAuthenticated, userRole, fetchUser, loading } = useAuth();

    if (loading.value) {
        await fetchUser();
    }

    const requiereAutenticacion = to.matched.some(record => record.meta.requiresAuth);
    const soloInvitados = to.matched.some(record => record.meta.guestOnly);
    const rolesPermitidos = to.meta.roles;

    if (requiereAutenticacion && !isAuthenticated.value) {
        return next({ name: 'Login' });
    }

    if (soloInvitados && isAuthenticated.value) {
        return next({ name: 'Home' });
    }

    if (rolesPermitidos && !rolesPermitidos.includes(userRole.value)) {
        return next({ name: 'Home' });
    }

    next();
});

export default router;
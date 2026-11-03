import app from 'flarum/forum/app';

/**
 * O link de cadastro do e-mail volta para o fórum com o token na sessão (nunca
 * na URL); o servidor o entrega no payload uma vez e aqui o SignUpModal abre.
 */
export default function openPendingRegistration() {
  const pending = (app.data as any).magicLinkRegistration as { token?: string; email?: string; username?: string } | undefined;

  if (!pending?.token) return;

  // Depois do app.mount(), quando o gerenciador de modais já existe.
  setTimeout(() => {
    app.modal.show(() => import('flarum/forum/components/SignUpModal'), {
      token: pending.token,
      email: pending.email || '',
      username: pending.username || '',
      provided: ['email'],
    });
  }, 0);
}

import app from 'flarum/forum/app';

import extendEditUserModal from '../common/extendEditUserModal';
import extendChangeEmailModal from './extenders/extendChangeEmailModal';
import extendForgotPasswordModal from './extenders/extendForgotPasswordModal';
import extendLogInModal from './extenders/extendLogInModal';
import extendSettingsPage from './extenders/extendSettingsPage';
import extendRequestErasureModal from './extenders/extendRequestErasureModal';
import extendSignUpModal from './extenders/extendSignUpModal';
import openPendingRegistration from './openPendingRegistration';

// Prioridade -100: roda depois dos temas (Avocado, Canela), que também
// estendem estes modais; assim os itens de senha que eles põem saem também.
app.initializers.add(
  'ramon-magic-link',
  () => {
    extendLogInModal();
    extendSignUpModal();
    extendChangeEmailModal();
    extendForgotPasswordModal();
    extendSettingsPage();
    extendRequestErasureModal();
    extendEditUserModal();
    openPendingRegistration();
  },
  -100
);

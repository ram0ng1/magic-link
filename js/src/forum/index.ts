import app from 'flarum/forum/app';

import extendEditUserModal from '../common/extendEditUserModal';
import extendChangeEmailModal from './extenders/extendChangeEmailModal';
import extendForgotPasswordModal from './extenders/extendForgotPasswordModal';
import extendLogInModal from './extenders/extendLogInModal';
import extendSettingsPage from './extenders/extendSettingsPage';
import extendSignUpModal from './extenders/extendSignUpModal';
import openPendingRegistration from './openPendingRegistration';

app.initializers.add('ramon-magic-link', () => {
  extendLogInModal();
  extendSignUpModal();
  extendChangeEmailModal();
  extendForgotPasswordModal();
  extendSettingsPage();
  extendEditUserModal();
  openPendingRegistration();
});

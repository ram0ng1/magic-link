import app from 'flarum/admin/app';

import extendEditUserModal from '../common/extendEditUserModal';
import extendCreateUserModal from './extendCreateUserModal';

export { default as extend } from './extend';

app.initializers.add('ramon-magic-link', () => {
  extendEditUserModal();
  extendCreateUserModal();
});

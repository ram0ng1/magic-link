<h1 align="center">Magic Link</h1>

<p align="center">
  <a href="https://github.com/ram0ng1/magic-link/actions/workflows/ci.yml"><img alt="CI" src="https://img.shields.io/github/actions/workflow/status/ram0ng1/magic-link/ci.yml?branch=main&style=flat-square&label=ci"></a>
  <a href="https://packagist.org/packages/ramon/magic-link"><img alt="Packagist" src="https://img.shields.io/packagist/v/ramon/magic-link?style=flat-square&label=packagist"></a>
  <img alt="Flarum" src="https://img.shields.io/badge/flarum-2.x-e7672e?style=flat-square">
  <a href="LICENSE"><img alt="License" src="https://img.shields.io/badge/license-MIT-blue?style=flat-square"></a>
  <a href="https://donate.stripe.com/fZe5o66nebkf39S28a"><img alt="Donate" src="https://img.shields.io/badge/donate-stripe-6772E5?style=flat-square"></a>
</p>

<p align="center">Passwordless sign in and sign up for Flarum 2. No password, nothing to leak.</p>

Members type their email (or username) and get a 6-digit code and a sign in link. Typing the code or clicking the link signs them in. Signing up works the same way: username and email, a code to confirm the address, and the account is created already confirmed.

## What it does

- **Every form, not only the log in.** Log in, sign up, change email, the admin's create and edit user, forgot password: none of them ask for a password anymore.
- **Nothing to steal.** The server refuses passwords (`/login`, `/api/token`, anything calling `User::checkPassword()`), never stores one (any password that reaches the database is erased) and turns password reset off. Old hashes are erased as members sign in, or all at once with `php flarum magic-link:purge-passwords`.
- **Code or link, your pick.** The code is single use, allows 5 tries and kills the link once used. The link opens a confirmation page, so email scanners that open it first don't sign anyone in. After its first use it stays valid for 10 minutes, for up to 7 uses.
- **No account discovery.** The answer is the same for emails with and without an account; nothing is sent to unknown emails. A honeypot catches bots, and requests are limited per IP and per email.
- **Changing email asks for a code** sent to the current address instead of the current password.
- **Plays well with others.** It extends the core `LogInModal` and `SignUpModal`, so the Avocado and Canela themes keep their look, social sign in (fof/oauth) keeps working and fields added by other extensions (terms, profile fields) are sent with the sign up. With `flarum/gdpr`, account erasure stops asking for a password.

## Installation

```sh
composer require ramon/magic-link
php flarum migrate
php flarum cache:clear
```

Enable Magic Link on the Extensions page and make sure your forum can send email (Admin → Email). Passwords stop working right away; switch **Also allow passwords** on in the settings if you want the email code as an extra option instead.

## Good to know

- If email delivery breaks, nobody can sign in. Anyone with server access can print a one-time sign in link with `php flarum magic-link:login-url <username>`; it works in maintenance mode too.
- Expired codes are deleted by the daily `magic-link:prune` command, run by the Flarum scheduler.
- Codes and links are stored only as SHA-256 hashes.

## Development

```sh
composer install
composer test:setup        # once: creates the SQLite test database
composer test              # unit + integration tests
composer analyse:phpstan
cd js && npm ci && npm run check-typings && npm run format-check && npm run build
```

## License

[MIT](LICENSE). Questions and ideas are welcome on the [issue tracker](https://github.com/ram0ng1/magic-link/issues).

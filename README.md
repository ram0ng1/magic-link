<p align="center">
  <img src="icon.svg" width="80" height="80" alt="Magic Link">
</p>

<h1 align="center">Magic Link</h1>

<p align="center">
  <a href="https://github.com/ram0ng1/magic-link/actions/workflows/ci.yml"><img alt="CI" src="https://img.shields.io/github/actions/workflow/status/ram0ng1/magic-link/ci.yml?branch=main&style=flat-square&label=ci"></a>
  <a href="https://packagist.org/packages/ramon/magic-link"><img alt="Packagist" src="https://img.shields.io/packagist/v/ramon/magic-link?style=flat-square&label=packagist"></a>
  <a href="https://packagist.org/packages/ramon/magic-link"><img alt="Downloads" src="https://img.shields.io/packagist/dt/ramon/magic-link?style=flat-square"></a>
  <img alt="Flarum" src="https://img.shields.io/badge/flarum-2.x-e7672e?style=flat-square">
  <a href="LICENSE"><img alt="License" src="https://img.shields.io/badge/license-MIT-blue?style=flat-square"></a>
  <a href="https://donate.stripe.com/fZe5o66nebkf39S28a"><img alt="Donate" src="https://img.shields.io/badge/donate-stripe-6772E5?style=flat-square"></a>
</p>

<p align="center">Passwordless sign in and sign up for Flarum 2. No password, nothing to leak.</p>

Members type their email (or username) and get a 6-digit code and a sign in link by email. Typing the code or clicking the link signs them in. Signing up works the same way: username and email, a code to confirm the address, and the account is created already confirmed.

Password login can stay on as an option, with "Email me a one-time login code" right under the email field. Everything uses Flarum's own log in and sign up modals, so the default theme, Avocado and Canela keep their look.

## What it does

- Every form without a password: log in, sign up, change email, forgot password, the admin's create and edit user, and account erasure with flarum/gdpr
- One box per digit for the code, with paste and one-time-code autofill on phones, a resend countdown and "use a different email address"
- With passwords off, the server refuses passwords everywhere, never stores one and turns password reset off; old hashes go away as members sign in, or all at once with `php flarum magic-link:purge-passwords`
- Only the latest email works; the code is single use, and using the code or the link burns the other
- The link opens a page with a button showing which account it is for, so email scanners that open it first don't sign anyone in
- The same answer for emails with and without an account, limits per IP and per email that count requests rather than accounts, and a honeypot for bots
- An account that was never confirmed loses whatever its creator left behind (password, sessions, social logins) when the real owner confirms it by code
- Sign up still finishes through Flarum's registration, so fields added by other extensions (terms, profile) and social sign in (fof/oauth) keep working

## Installation

```sh
composer require ramon/magic-link
php flarum migrate
php flarum cache:clear
```

Enable Magic Link on the Extensions page and make sure your forum can send email (Admin → Email). Passwords stop working right away; switch **Allow log in with password** on in the settings to keep them alongside the email code. Expired codes are deleted by the daily `magic-link:prune` command, so run `php flarum schedule:run` from cron every minute.

## Good to know

- If email delivery breaks, nobody can sign in. Anyone with server access can print a one-time sign in link with `php flarum magic-link:login-url <username>`; it also works in maintenance mode.
- With the default `sync` queue the email is sent during the request. A real queue (Redis, database) keeps response times the same for every address.
- Behind a reverse proxy or CDN, make sure PHP sees the visitor's IP (for example nginx `real_ip`), otherwise everyone shares the per-IP limit.
- Codes and links are stored only as SHA-256 hashes.

## Languages

Brazilian Portuguese and English ship with the extension. Core strings come from the Flarum language packs.

## Credits

Magic Link is maintained by [Ramon Guilherme](https://ramonguilherme.com.br).

## License

[MIT](LICENSE). Ideas and bug reports are welcome on the [issue tracker](https://github.com/ram0ng1/magic-link/issues).

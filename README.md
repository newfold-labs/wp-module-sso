<a href="https://newfold.com/" target="_blank">
    <img src="https://newfold.com/content/experience-fragments/newfold/site-header/master/_jcr_content/root/header/logo.coreimg.svg/1621395071423/newfold-digital.svg" alt="Newfold Logo" title="Newfold Digital" align="right" 
height="42" />
</a>

# WordPress SSO Module
[![Version Number](https://img.shields.io/github/v/release/newfold-labs/wp-module-sso?color=21a0ed&labelColor=333333)](https://github.com/newfold/wp-module-sso/releases)
[![License](https://img.shields.io/github/license/newfold-labs/wp-module-sso?labelColor=333333&color=666666)](https://raw.githubusercontent.com/newfold-labs/wp-module-sso/master/LICENSE)

Single sign-on functionality for WordPress.

## Module Responsibilities

- Allows a user to log in to a website automatically using a generated one-time-use link.
- Login links can be created using two methods:
    - WP-CLI
    - WP REST API
- Generated login links use AJAX to handle login.
- There is a legacy AJAX method that uses the `sso-check` action and a new AJAX method that uses the `newfold_sso_login` action.
- In most cases, our internal systems generate login links using WP-CLI.

## Critical Paths

- SSO links can be generated via WP-CLI
- SSO links can be generated via the WP REST API
- SSO links successfully log in a user

## Installation

### 1. Add the Newfold Satis to your `composer.json`.

 ```bash
 composer config repositories.newfold composer https://newfold-labs.github.io/satis/
 ```

### 2. Require the `newfold-labs/wp-module-sso` package.

 ```bash
 composer require newfold-labs/wp-module-sso
 ```

[More on NewFold WordPress Modules](https://github.com/newfold-labs/wp-module-loader)

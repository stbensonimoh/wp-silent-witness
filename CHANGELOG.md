# Changelog

## [4.0.0](https://github.com/stbensonimoh/wp-silent-witness/compare/v3.0.0...v4.0.0) (2026-03-07)


### ⚠ BREAKING CHANGES

* v4.0.0 requires PHP 8.1+, plugin renamed to 'Silent Witness for WordPress' for WordPress.org compliance

### Documentation

* add technical specification for v4.0.0 ([b85fa60](https://github.com/stbensonimoh/wp-silent-witness/commit/b85fa60cb03b6ed48a35685c7d382779367f14f3))

## [3.0.0](https://github.com/stbensonimoh/wp-silent-witness/compare/v2.2.1...v3.0.0) (2026-02-23)


### ⚠ BREAKING CHANGES

* Hash algorithm migration requires table truncation

### Features

* modernize hash algorithm and PHP constraints (v2.3.0) ([d86d0bf](https://github.com/stbensonimoh/wp-silent-witness/commit/d86d0bf4869d0f869f02582b1948b2b3c6466052))
* modernize hash algorithm and PHP constraints (v2.3.0) ([17d95c7](https://github.com/stbensonimoh/wp-silent-witness/commit/17d95c7e7d0f2c8dbf17fb4ab3e51e895a6c24c0)), closes [#22](https://github.com/stbensonimoh/wp-silent-witness/issues/22) [#24](https://github.com/stbensonimoh/wp-silent-witness/issues/24)


### Bug Fixes

* address Copilot review comments on PR [#39](https://github.com/stbensonimoh/wp-silent-witness/issues/39) ([0d96eca](https://github.com/stbensonimoh/wp-silent-witness/commit/0d96eca48d20d1e49e5cbe0d22803242f99d69a6))
* address new Copilot review comments ([be37db2](https://github.com/stbensonimoh/wp-silent-witness/commit/be37db280decc42eb9dba668206dfc39439d8deb))

## [2.2.1](https://github.com/stbensonimoh/wp-silent-witness/compare/v2.2.0...v2.2.1) (2026-02-22)


### Bug Fixes

* address PR review feedback from Copilot ([71f47db](https://github.com/stbensonimoh/wp-silent-witness/commit/71f47db45fbb1145363568bfca6a9da78c0f64d5))
* address review feedback on PR [#37](https://github.com/stbensonimoh/wp-silent-witness/issues/37) ([5aa7a6d](https://github.com/stbensonimoh/wp-silent-witness/commit/5aa7a6dcd84a23ff79bcb13e6f9fb0b873dbfc1b))
* correct remaining PHPCS ignore placements ([5c4599b](https://github.com/stbensonimoh/wp-silent-witness/commit/5c4599b63b0e340ee30cd54ce730d90502603f97))
* replace phpcs:ignore with disable/enable blocks for WPCS InterpolatedNotPrepared errors ([5db7fa4](https://github.com/stbensonimoh/wp-silent-witness/commit/5db7fa48cda58c0e97ac32799f1d87f57f2f8802))
* resolve all PHPCS violations to pass CI lint checks ([97b028c](https://github.com/stbensonimoh/wp-silent-witness/commit/97b028c93474a4a6d35cffaea4681fd4cd51464f))
* resolve remaining phpcs warnings in CI ([5e452dc](https://github.com/stbensonimoh/wp-silent-witness/commit/5e452dca3b086c4073aee65258aa193a5100b12d))
* resolve WPCS violations causing CI failures in PR [#37](https://github.com/stbensonimoh/wp-silent-witness/issues/37) ([4bc048c](https://github.com/stbensonimoh/wp-silent-witness/commit/4bc048c0604b6108fe74742f2a0656b053dbd014))
* rewrite while loop to eliminate assignment-in-condition PHPCS warning ([b4b41f0](https://github.com/stbensonimoh/wp-silent-witness/commit/b4b41f0a4b1733472b4ded59cca35589e85b0d23))
* suppress DirectDatabaseQuery phpcs warnings on INSERT query ([f1451fc](https://github.com/stbensonimoh/wp-silent-witness/commit/f1451fc29de85c500ba047217b6d85fd7209bf45))

## [2.2.0](https://github.com/stbensonimoh/wp-silent-witness/compare/v2.1.0...v2.2.0) (2026-02-22)


### Features

* add internal 15-minute cron schedule (v2.0.1) ([8b24ee6](https://github.com/stbensonimoh/wp-silent-witness/commit/8b24ee66f272d373f6220951ba70decc3cc95b87))
* add uninstall logic and destroy command for neat tear down ([b6ddd0b](https://github.com/stbensonimoh/wp-silent-witness/commit/b6ddd0b957f95fa41503868e5133d6f964fb8e33))
* internationalize user-facing strings ([4993a1a](https://github.com/stbensonimoh/wp-silent-witness/commit/4993a1af9cc0333e403df1832db20bb791a2028b))
* internationalize user-facing strings and establish translation domain ([d296818](https://github.com/stbensonimoh/wp-silent-witness/commit/d29681860b987a54113a1b593bcd91f57a1ce24b))
* pivot to log-ingestion strategy with fseek efficiency (v2.0.0) ([c1dd08a](https://github.com/stbensonimoh/wp-silent-witness/commit/c1dd08a21d8ae8526d30d8cb77bf75ad5cb706a8))


### Bug Fixes

* add check-db command and deeper diagnostic feedback (v1.0.3) ([2b4bf7f](https://github.com/stbensonimoh/wp-silent-witness/commit/2b4bf7f4c6f089b931c29806674f0a16c2366e3e))
* add multisite support using base_prefix and site_transients (v1.0.4) ([7396c75](https://github.com/stbensonimoh/wp-silent-witness/commit/7396c7569f97c5af9cec3f2fa1e198d5024db352))
* add trailing newline to workflow ([8fef4e0](https://github.com/stbensonimoh/wp-silent-witness/commit/8fef4e05919ea8e47a00ed0d5def58b288d29dc5))
* address review comments on PR [#25](https://github.com/stbensonimoh/wp-silent-witness/issues/25) ([e5105bd](https://github.com/stbensonimoh/wp-silent-witness/commit/e5105bd1f2944c251e5130ac81a2c9ca0ece054a))
* correct Author URI to stbensonimoh.com ([2eccf68](https://github.com/stbensonimoh/wp-silent-witness/commit/2eccf68c3c4c08565a3968e4e04f4aa6fcd34c78))
* harden error capture and add diagnostic test command (v1.0.2) ([fceffb9](https://github.com/stbensonimoh/wp-silent-witness/commit/fceffb9883dec93844f3fcc797997df73f4a0a41))
* multi-stage handler re-registration (v1.0.7) ([39f4657](https://github.com/stbensonimoh/wp-silent-witness/commit/39f4657a84cb31bf8d7ac5f0b5ccd2ddf786babc))
* nuclear priority error handler registration (v1.0.6) ([3023272](https://github.com/stbensonimoh/wp-silent-witness/commit/302327296a271c6cfcdfe5e3ed002fee3a561411))
* replace Release Please extra-files with custom workflow step for WordPress headers ([7410b69](https://github.com/stbensonimoh/wp-silent-witness/commit/7410b69deaec247084cdad65754993940dcedbf7))
* replace Release Please extra-files with custom workflow step for WordPress headers ([de3406b](https://github.com/stbensonimoh/wp-silent-witness/commit/de3406b1c86dfbd3258aedc1ee8d2802b79231cb))
* resolve undefined variable $action in cli_command and update version to 1.0.1 ([3ce0756](https://github.com/stbensonimoh/wp-silent-witness/commit/3ce0756fed5650a99a7f78fe1dfb0fe58488e701))
* simplify and add direct DB test (v1.0.5) ([7a6c0d1](https://github.com/stbensonimoh/wp-silent-witness/commit/7a6c0d1ed4a81a4bb12c76a09af5c888af9534e2))
* use correct Release Please config for WordPress version strings ([92f9ba5](https://github.com/stbensonimoh/wp-silent-witness/commit/92f9ba523ae6f7afd0181785137ed3f42efb6d30))
* use generic release type with version-file for WordPress ([0a0fda9](https://github.com/stbensonimoh/wp-silent-witness/commit/0a0fda9da9802a6a1396f99ad59c74736ba36dfe))
* use simple release type for WordPress plugin versioning ([112829a](https://github.com/stbensonimoh/wp-silent-witness/commit/112829af30c26b76c21206a5b37c431bd1109cdc))
* use simple release type with all files in extra-files ([93fb72b](https://github.com/stbensonimoh/wp-silent-witness/commit/93fb72ba66015b09e5c0194ebd2e8c00074dffc9))

## [2.1.0](https://github.com/stbensonimoh/wp-silent-witness/compare/wp-silent-witness-v2.0.1...wp-silent-witness-v2.1.0) (2026-02-22)


### Features

* add internal 15-minute cron schedule (v2.0.1) ([8b24ee6](https://github.com/stbensonimoh/wp-silent-witness/commit/8b24ee66f272d373f6220951ba70decc3cc95b87))
* add uninstall logic and destroy command for neat tear down ([b6ddd0b](https://github.com/stbensonimoh/wp-silent-witness/commit/b6ddd0b957f95fa41503868e5133d6f964fb8e33))
* internationalize user-facing strings ([4993a1a](https://github.com/stbensonimoh/wp-silent-witness/commit/4993a1af9cc0333e403df1832db20bb791a2028b))
* internationalize user-facing strings and establish translation domain ([d296818](https://github.com/stbensonimoh/wp-silent-witness/commit/d29681860b987a54113a1b593bcd91f57a1ce24b))
* pivot to log-ingestion strategy with fseek efficiency (v2.0.0) ([c1dd08a](https://github.com/stbensonimoh/wp-silent-witness/commit/c1dd08a21d8ae8526d30d8cb77bf75ad5cb706a8))


### Bug Fixes

* add check-db command and deeper diagnostic feedback (v1.0.3) ([2b4bf7f](https://github.com/stbensonimoh/wp-silent-witness/commit/2b4bf7f4c6f089b931c29806674f0a16c2366e3e))
* add multisite support using base_prefix and site_transients (v1.0.4) ([7396c75](https://github.com/stbensonimoh/wp-silent-witness/commit/7396c7569f97c5af9cec3f2fa1e198d5024db352))
* add trailing newline to workflow ([8fef4e0](https://github.com/stbensonimoh/wp-silent-witness/commit/8fef4e05919ea8e47a00ed0d5def58b288d29dc5))
* address review comments on PR [#25](https://github.com/stbensonimoh/wp-silent-witness/issues/25) ([e5105bd](https://github.com/stbensonimoh/wp-silent-witness/commit/e5105bd1f2944c251e5130ac81a2c9ca0ece054a))
* correct Author URI to stbensonimoh.com ([2eccf68](https://github.com/stbensonimoh/wp-silent-witness/commit/2eccf68c3c4c08565a3968e4e04f4aa6fcd34c78))
* harden error capture and add diagnostic test command (v1.0.2) ([fceffb9](https://github.com/stbensonimoh/wp-silent-witness/commit/fceffb9883dec93844f3fcc797997df73f4a0a41))
* multi-stage handler re-registration (v1.0.7) ([39f4657](https://github.com/stbensonimoh/wp-silent-witness/commit/39f4657a84cb31bf8d7ac5f0b5ccd2ddf786babc))
* nuclear priority error handler registration (v1.0.6) ([3023272](https://github.com/stbensonimoh/wp-silent-witness/commit/302327296a271c6cfcdfe5e3ed002fee3a561411))
* resolve undefined variable $action in cli_command and update version to 1.0.1 ([3ce0756](https://github.com/stbensonimoh/wp-silent-witness/commit/3ce0756fed5650a99a7f78fe1dfb0fe58488e701))
* simplify and add direct DB test (v1.0.5) ([7a6c0d1](https://github.com/stbensonimoh/wp-silent-witness/commit/7a6c0d1ed4a81a4bb12c76a09af5c888af9534e2))
* use correct Release Please config for WordPress version strings ([92f9ba5](https://github.com/stbensonimoh/wp-silent-witness/commit/92f9ba523ae6f7afd0181785137ed3f42efb6d30))
* use generic release type with version-file for WordPress ([0a0fda9](https://github.com/stbensonimoh/wp-silent-witness/commit/0a0fda9da9802a6a1396f99ad59c74736ba36dfe))
* use simple release type for WordPress plugin versioning ([112829a](https://github.com/stbensonimoh/wp-silent-witness/commit/112829af30c26b76c21206a5b37c431bd1109cdc))
* use simple release type with all files in extra-files ([93fb72b](https://github.com/stbensonimoh/wp-silent-witness/commit/93fb72ba66015b09e5c0194ebd2e8c00074dffc9))

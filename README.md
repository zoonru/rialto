# Rialto

[![PHP Version](https://img.shields.io/packagist/php-v/zoon/rialto.svg?style=flat-square)](http://php.net/)
[![Composer Version](https://img.shields.io/packagist/v/zoon/rialto.svg?style=flat-square&label=Composer)](https://packagist.org/packages/zoon/rialto)
[![Node Version](https://img.shields.io/node/v/@nesk/rialto.svg?style=flat-square&label=Node)](https://nodejs.org/)
[![NPM Version](https://img.shields.io/npm/v/@nesk/rialto.svg?style=flat-square&label=NPM)](https://www.npmjs.com/package/@zoon/rialto)
[![Build Status](https://img.shields.io/travis/zoonru/rialto.svg?style=flat-square&label=Build%20Status)](https://travis-ci.org/zoon/rialto)

A package to manage Node resources from PHP. It can be used to create bridges to interact with Node libraries in PHP, like [PuPHPeteer](https://github.com/zoonru/puphpeteer/).

It works by creating a Node process and communicates with it through sockets.

## Requirements and installation

Rialto requires PHP >= 7.2 and Node >= 8.

Install it in your project:

```shell
composer require zoon/rialto
npm install https://github.com/zoonru/rialto
```

## Usage

See our tutorial to [create your first bridge with Rialto](docs/tutorial.md).

An [API documentation](docs/api.md) is also available.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

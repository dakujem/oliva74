# Oliva 🌳 (backport for PHP 7.4)

>
> 💿 `composer require dakujem/oliva74`
>

This is a backport of the original package [dakujem/oliva](https://github.com/dakujem/oliva) for **PHP 7.4** only.


## Documentation

Please refer to the documentation of the original package:  
👉 [dakujem/oliva](https://github.com/dakujem/oliva) 👈


## Migration to PHP 8

This backport is based on [`v1.0`](https://github.com/dakujem/oliva/releases/tag/1.0) release of `dakujem/oliva`
with removed PHP 8 features (most notably compound type hints).  
It requires PHP 7.4, the original works with PHP 8 and later versions.

After updating your project to PHP 8, change the requirement to `dakujem/oliva`.  
Chances are, you need not do anything else.

If your classes directly implement any of the interfaces or extend the `Node` class,
you will also need to update the type hints.


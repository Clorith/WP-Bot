WordPress IRC Contributors
=========

The WP IRC Contributors project is an IRC bot intended to automatically identify (and eventually credit) users who contribute to the WordPress project by providing support and guidance over IRC. Much like the forums, which are somewhat easier to track users engagement, the IRC channel lacks such an identifying factor, which this project looks to remedy.

Currently there are multiple metrics being recorded to try and identify which one (or combination of ones) that best serves our purpose to keep things fair, but at the same time avoid people gaming the system for internet points.

Dependencies
-----------

The bot is ran on PHP, both to keep it portable, but also because the WordPress project already runs a few resources this way and by utilizing the same libraries we ensure that the project doesn't stop if something happens to a single person:

* PHP
* SQLite3 - Portable database, yay!
* [SmartIRC] - A PHP library for interacting with IRC

Install & Run
--------------

```sh
git clone https://github.com/Clorith/WP-IRC-Contributors WP-IRC-Contributors
cd WP-IRC-Contributors/IRC
php contributor-bot.php
```

General configurations are done in `config.php`

License
----

We use the GPLv2 license which allows anyone to play around with our code in any way they like, have fun!

[SmartIRC]:https://github.com/pear/Net_SmartIRC

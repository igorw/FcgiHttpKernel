# FcgiHttpKernel

Port of [CgiHttpKernel](https://github.com/igorw/CgiHttpKernel) to FastCGI
(FCGI).

This is the same idea as the CGI kernel, but faster. And slightly more
annoying, because you need to configure a port and bind to it. But as a
result, it's *significantly* faster.

It enables testing at the FastCGI process boundary. It's compatible with any
process that exposes a FastCGI interface. It's compatible with any library
that consumes an HttpKernel.

## Stability

There are still some bugs (see skipped tests). Pull requests highly
appreciated.

## Credits

Based on [adoy/PHP-FastCGI-Client](https://github.com/adoy/PHP-FastCGI-Client)
and [igorw/CgiHttpKernel](https://github.com/igorw/CgiHttpKernel).

## References

* [FastCGI spec](http://www.fastcgi.com/drupal/node/6?q=node/22)

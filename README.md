# WHMCS registrar module for domreg.lt

## About

This is the WHMCS domain registrar module which handles .LT domains (lithuanian
TLD). Module is dedicated to registrar [domreg.lt].


## Status

As of date (2015-04-17), the module is in some development, with all main
functions implemented:

* Domain registration, transferring, renewal and deletion;
* Registrant contact editing;
* Nameserver management;
* Nameserver grouping;
* Domain sync with crontab.

Module is still considered unstable because of lack of proper automated testing
and specifics of WHMCS, so you take the risk of getting some unexpected bugs,
which you should report to me by email.


## Installation

Drop the folder `/domreg` into `/whmcs/modules/registrars`, configure and
activate.

Before using the module, you must prepare some data on Domreg's
[registrar page][registrar]:

* Create a single tech contact, which is a requirement in module configuration.
* Create several nameserver groups. Normally you would want to create at least 1
group - a group with default nameservers for your hosting. Then, enter this
group's **name** into configuration field. After doing that, new domain entries
will have only 1 group entry instead of N nameserver entries.

In WHMCS client must have custom field "Company ID" which sends as orgcode.

## Community

The Domreg module is shared openly with the community because of the demand for
modules that support custom EPP implementations. This module currently supports
only Domreg registrar and it's fitted to our business requirements.

We heavily depend on your support for improving this module, so your help is
appreciated!

[![donate][paypal_button]][paypal_link]

Our supporters:

* [happyhosting.lt]
* [sprendimumedija.lt]
* [bacloud.com]

## License

Source code is covered by [LGPL-3 license][license].

This module is provided by [happyhosting.lt].


## Contacts

Aleksej Komarov <[stylemistake@gmail.com]>

[license]: LICENSE.md
[paypal_link]: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FU6CN3MMFV52Q
[paypal_button]: https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif
[domreg.lt]: http://www.domreg.lt/
[registrar]: http://www.domreg.lt/registrar/
[happyhosting.lt]: http://happyhosting.lt/
[sprendimumedija.lt]: http://sprendimumedija.lt/
[stylemistake@gmail.com]: mailto:stylemistake@gmail.com
[stylemistake.com]: http://stylemistake.com/
[bacloud.com]: https://www.bacloud.com/

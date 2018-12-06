# WHMCS registrar module for domreg.lt

## About

This is the WHMCS domain registrar module which handles .LT domains (lithuanian
TLD). Module is dedicated to registrar [www.domreg.lt](http://www.domreg.lt).


## Installation

**[Download a release](https://github.com/stylemistake/whmcs-domreg/releases)**.

Contents of this release must be placed into `/whmcs/modules/registrars/domreg`.

Configure and activate this module through WHMCS admin area.

Before using the module, you must make some preparations on
[registrar page](http://domreg.lt/registrar):

- Create a single tech contact (write down the resulting ID).
- Create several nameserver groups.

> Normally you would want to create at least 1 nameserver group - a group
of default nameservers which you provide as part of your hosting. Enter this
group's **name** into module configuration.

> ***DO NOT*** write nameserver adresses info module configuration directly.
Create a nameserver group, and write that group name instead.

After you completed these steps, you should be ready to register domains.


## Setup for developers

If you downloaded or cloned this repository directly instead of downloading
a release, you will need to do one extra step. Run:

```
make
```

This will download `composer.phar`, download project dependencies and
setup autoloading.

This is the only step necessary before installing the module into WHMCS.


## Operation details

Domreg API is based on a custom EPP protocol with the following modifications:

- It uses two types of contact objects:
  - Registrant (person who registered the domain)
  - Tech contact (hosting provider or other support contact)
- Nameservers can be joined into nameserver groups.

This means that some functions of this module are non-standard and
have custom behaviour.


### Registrant objects

To maintain consistency with Domreg specification, each WHMCS client is
connected to a registrant object in Domreg. Connections are stored in
`mod_domreg_registrants` table, which is created automatically when
you activate the module.

Module tries its best to:

- Guess a correct RN (registrant number), when domain is being imported
  or transferred;
- Use existing RN if WHMCS user already has it.

If module doesn't find an existing registrant object, it creates a new one.

Be aware, all contact information of user's domains is interconnected
because domains use same registrant objects. If you change contact details
on one domain - it changes on the rest of domains. Keep that in mind.

All domains registered by one WHMCS user can belong to only one registrant
object. Due to WHMCS complexity, it is impossible for one user to have
multiple registrant objects (or in other words, different contact details).


### Nameserver groups

Module automatically joins nameservers into groups when operating EPP
protocol. To be able to leverage this feature, make sure you created a
nameserver group in registrar area and declared it in module configuration.

Module tries to use these groups to compact nameserver information of a
domain when domain is registered or nameserver information is edited.


### Additional fields

When you manage a domain from admin area, you can see these new fields:

- Domain RN - RN associated with this domain;
- Client RN - RN associated with this WHMCS account.

Domain RN is not editable, because it is a part of the domain. RN change
counts as a trade operation, and this is not what you usually want to do.

Client RN is what you can edit, and it is the RN of a WHMCS client. When
WHMCS client registers a new domain, this number is used to refer to the
same registrant object in Domreg.

If this field is empty, it will be filled as soon as client registers
a new domain.


## Community

Domreg module is shared openly with the community because there is a
demand for modules that support custom EPP implementations. This module
currently supports only Domreg registrar and it's fitted to our business
requirements.

We depend on your support for improving this module, so your help is
appreciated!

[![donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FU6CN3MMFV52Q)

Our supporters:

* [happyhosting.lt](http://happyhosting.lt/)
* [hoston.lt](http://hoston.lt/)


## License

Copyright (c) 2018 Aleksej Komarov

Source code is provided under GPLv2 license.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

You are free to use and run this module as part of your WHMCS website,
with no extra actions required, unless you make changes to source code
as stated above.

It is your duty to provide these changes back to the author. Community
will appreciate your effort, and will ensure that this package is
maintained over long time.

This program is distributed in the hope that it will be useful, but
**WITHOUT ANY WARRANTY**; without even the implied warranty of
**MERCHANTABILITY** or **FITNESS FOR A PARTICULAR PURPOSE**. See the
GNU General Public License for more details.


## Contacts

Aleksej Komarov

Email: stylemistake@gmail.com

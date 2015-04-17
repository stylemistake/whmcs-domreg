## Technical

Domreg API is based on a custom EPP protocol with the following modifications:

* It uses registrant and tech contact objects (each with unique id); Nameservers
* can be joined in groups.

This means that some functions of the module are non-standard and have custom
behaviour.


### Registrant objects

To maintain consistency with Domreg specification, each WHMCS client is
connected to a registrant object in Domreg. Connections are stored by default in
`mod_domreg_registrants` table, which is created automatically when you activate
the module.

Module tries its best to:

* Guess a correct RN (registrant number), when domain is being imported or
* transferred; Use existing RN if WHMCS user already has it;

If module doesn't find RN, it creates a new registrant object.

Be aware, that in this case, all contact information of user's domains is
interconnected because domains use the same registrant objects. If you change
contacts of one domain - it changes everywhere. Keep that in mind.

Also, because WHMCS doesn't use special user IDs for multiple user's contacts,
user cannot register domains with different contact details - all his domains
will always use one registrant object by default.


### Nameserver groups

Module automatically joins nameservers into groups when operating EPP protocol.
To be able to use this, make sure you defined common nameserver groups in module
configuration. Module tries to use these groups to compact nameserver
information of a domain when domain is registered or nameserver information is
edited.


### Additional fields

When you manage a domain from admin area, you can see these new fields:

* Domain RN - RN associated with this domain; Client RN - RN associated with
* this WHMCS account.

Domain RN is not editable. RN change counts as a trade operation, and this is
not what you usually have to do.

Client RN is what you can edit, and it is the RN used by a client when he
registers a new domain. If this field is empty, it will be filled as soon as
client registers a new domain, by the way creating a new registrant object.

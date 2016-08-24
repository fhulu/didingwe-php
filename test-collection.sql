select u.identifier, c.identifier, c.value
from collection u join collection c on u.collection='user' and c.collection = 'country'
and u.attribute = 'country' and u.value = c.identifier

select u.identifier,  ufn.value first_name, uln.value last_name, ul.value language_code, l.value language,  u.create_time
from collection u
left join collection ufn on ufn.collection = 'user' and ufn.version = u.version and ufn.identifier = u.identifier and ufn.attribute = 'first_name'
left join collection uln on uln.collection = 'user' and uln.version = u.version and uln.identifier = u.identifier and uln.attribute = 'last_name'
left join collection ul on ul.collection = 'user' and ul.version = u.version  and ul.identifier = u.identifier and ul.attribute = 'language'
left join collection l on l.collection = 'language' and l.version = u.version and ul.value = l.identifier and l.attribute = 'name'
where u.collection = 'user' and u.attribute = 'password' and u.version = 0

select identifier email, value first_name,
(select value from collection where collection = m.collection and version <= m.version and identifier=m.identifier and attribute = 'last_name' order by version desc limit 1) last_name,
(select value from collection where collection = 'language' and version <= m.version and identifier = (
  select value from collection where collection = m.collection and version <= m.version and identifier=m.identifier and attribute = 'language' order by version desc limit 1)
  order by version desc limit 1) language
from collection m
where collection = 'user' and attribute = 'first_name' and version = 0

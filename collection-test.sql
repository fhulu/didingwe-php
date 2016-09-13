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
(select value from collection where collection = 'language' and version <= m.version and attribute = 'name' and identifier = (
  select value from collection where collection = m.collection and version <= m.version and identifier=m.identifier and attribute = 'language' order by version desc limit 1)
  order by version desc limit 1) language
from collection m
where collection = 'user' and attribute = 'first_name' and version = 0


# selection
select m.identifier email, m.value first_name,
(select value from collection where collection = m.collection and version <= m.version and identifier=m.identifier and attribute = 'last_name' order by version desc limit 1) last_name,
(select value from collection where collection = m.collection and version <= m.version and identifier=m.identifier and attribute = 'cellphone' order by version desc limit 1) cellphone,
(select value from collection where collection = m.collection and version <= m.version and identifier=m.identifier and attribute = 'role' order by version desc limit 1) role
from collection m
join collection m1 on m1.collection = m.collection and m1.version <= m.version and m1.identifier=m.identifier and m1.attribute = 'active' and m1.value = '1'
where m.collection = 'user' and m.version = 0 and m.identifier = 'fhulu@mukoni.co.za' and m.attribute = 'first_name'


# search
select identifier email,
  max(case when attribute='first_name' then value end) first_name,
  max(case when attribute='last_name' then value end) last_name,
  max(case when attribute='cellphone' then value end) cellphone,
  max(case when attribute='active' then value end) active
from
(SELECT m.identifier,m.attribute,m.value FROM `collection` m
  join collection m1 on m1.collection = m.collection and m1.version <= m.version and m1.identifier=m.identifier and m1.attribute = 'active' and m1.value = '1'
  where m.collection = 'user' and m.version <= 0
  and (m.identifier like '%a%' or m.value like 'a%')

 ) tmp
group by identifier

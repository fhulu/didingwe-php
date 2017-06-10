-- select time_added, cellphone, contact_info, access_level from contact
select identifier, attribute,value from contact
  where collection = 'contact' and attribute in ('time_added','cellphone','contact_info', 'access_level')
  order by identifier
--0.0005 seconds

-- select time_added, cellphone, contact_info, access_level from contact order by cellphone
select contact.identifier, contact.attribute, contact.value from contact `contact`
  left join contact sorter
      on `contact`.collection = sorter.collection and `contact`.identifier = sorter.identifier and sorter.attribute = 'cellphone'
  where contact.collection = 'contact' and contact.attribute in ('time_added','cellphone','contact_info', 'access_level')
  order by sorter.value, contact.identifier
   --0.0470 seconds

 select contact.identifier, contact.attribute, contact.value from contact `contact`
   left join (select value, identifier from contact where collection = `contact`.collection and attribute = 'cellphone'
        ) sorter
       on `contact`.identifier = sorter.identifier
   where contact.collection = 'contact' and contact.attribute in ('time_added','cellphone','contact_info', 'access_level')
   order by sorter.value, contact.identifier
      --0.0470 seconds

-- select time_added, cellphone, contact_info, access_level from contact where owner = 527
select `contact`.identifier, `contact`.attribute, `contact`.value
  from contact `contact`
    join contact `owner`
      on  `owner`.collection = `contact`.collection and `contact`.identifier = `owner`.identifier
      and `owner`.attribute = 'owner' and `owner`.value = 527
    where `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level')
  order by 1
  -- 0.0149

-- select time_added, cellphone, contact_info, access_level from contact where owner = 527 and contact_info like '%ANC%'
select `contact`.identifier, `contact`.attribute, `contact`.value
  from contact `contact`
    join contact `owner`
      on  `owner`.collection = `contact`.collection and `contact`.identifier = `owner`.identifier
      and `owner`.attribute = 'owner' and `owner`.value = 527
    join contact `filter_contact_info`
      on `filter_contact_info`.collection = `contact`.collection and `contact`.identifier = `filter_contact_info`.identifier
      and `filter_contact_info`.attribute = 'contact_info' and `filter_contact_info`.value like '%ANC%'
    where `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level')
  order by 1
    -- 0.0046

-- select time_added, cellphone, contact_info, access_level from contact where owner = 527 and any value like '%a%'
select `contact`.identifier, `contact`.attribute, `contact`.value
  from contact `contact`
    join contact `owner`
      on  `owner`.collection = `contact`.collection and `contact`.identifier = `owner`.identifier
      and `owner`.attribute = 'owner' and `owner`.value = 527
    join contact `searcher`
      on  `searcher`.collection = `contact`.collection and `contact`.identifier = `searcher`.identifier
      and `searcher`.value like '%a%' and `searcher`.attribute in ('time_added','cellphone','contact_info','access_level')
    where `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level')
  order by 1
  -- 0.0135

-- select time_added, cellphone, contact_info, access_level from contact where owner = 527 order by cellphone
select `contact`.identifier, `contact`.attribute, `contact`.value
  from contact `contact`
    join contact `owner`
      on  `owner`.collection = `contact`.collection and `contact`.identifier = `owner`.identifier
      and `owner`.attribute = 'owner' and `owner`.value = 527
      left join contact sorter
          on `contact`.collection = sorter.collection and `contact`.identifier = sorter.identifier and sorter.attribute = 'cellphone'
    where `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level')
  order by sorter.value, contact.identifier
  --0.0148 seconds

    --0.0407 seconds

-- select time_added, cellphone, contact_info, access_level,  if(blacklisted,'whitelist','blacklist') actions, from contact where owner = 527
select `contact`.identifier, `contact`.attribute, `contact`.value
  from contact `contact`
   join contact `owner`
      on  `owner`.collection = `contact`.collection and `contact`.identifier = `owner`.identifier
      and `owner`.attribute = 'owner' and `owner`.value = 527
    where `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level')
  union
  select `contact`.identifier, 'actions' attribute, if(`contact`.value,'whitelist','blacklist')
    from contact `contact`
     join contact `owner`
        on  `owner`.collection = `contact`.collection and `contact`.identifier = `owner`.identifier
        and `owner`.attribute = 'owner' and `owner`.value = 527
      where `contact`.collection = 'contact' and `contact`.attribute in ('blacklisted')
    order by 1
  --0.0324 seconds

  select `contact`.identifier,
     if(`contact`.attribute='blacklisted','actions', `contact`.attribute) `attribute`, if(`contact`.attribute='blacklisted',
        if(`contact`.value,'whitelist','blacklist'),contact.value) `value`
    from contact `contact`
      join contact `owner`
        on  `owner`.collection = `contact`.collection and `contact`.identifier = `owner`.identifier
        and `owner`.attribute = 'owner' and `owner`.value = 527
    where `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level', 'blacklisted')
    order by 1
    --0.0187

  -- select time_added, cellphone, contact_info, access_level,  if(blacklisted,'whitelist','blacklist') actions, from contact where owner = 527
  select `contact`.identifier,
     if(`contact`.attribute='blacklisted','actions', `contact`.attribute) `attribute`, if(`contact`.attribute='blacklisted',
        if(`contact`.value,'whitelist','blacklist'),contact.value) `value`
    from contact `contact`
      join contact `owner`
        on  `owner`.collection = `contact`.collection and `contact`.identifier = `owner`.identifier
        and `owner`.attribute = 'owner' and `owner`.value = 527
    left join contact sorter
        on `contact`.collection = sorter.collection and `contact`.identifier = sorter.identifier and sorter.attribute = 'cellphone'
    where `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level', 'blacklisted')
    order by sorter.value, contact.identifier
    --0.0500 seconds

-- select time_added, cellphone, contact_info, access_level, u.email , u.first_name
-- from contact c join user u on c.owner = user.id on where c.partner  = 526 and c.active = 1
select `contact`.identifier, ifnull(user.attribute, `contact`.attribute), ifnull(user.value,`contact`.value)
  from contact `contact`
    join contact `active`
      on `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level','owner')
      and  `active`.collection = `contact`.collection and `contact`.identifier = `active`.identifier
      and `active`.attribute = 'active' and `active`.value = 1
    join contact `partner`
      on  `partner`.collection = `contact`.collection and `contact`.identifier = `partner`.identifier
      and `partner`.attribute = 'partner' and `partner`.value = 526
    left join auth `user`
        on `user`.collection = 'user' and `user`.attribute in ('email','first_name')
        and `contact`.collection = 'contact' and `contact`.attribute = 'owner'
        and `user`.identifier = `contact`.value
  order by 1
-- 0.0604 seconds

-- select time_added, cellphone, contact_info, access_level, u.email , u.first_name
-- from contact c join user u on c.owner = user.id on where c.partner  = 526 and c.active = 1
-- order by c.cellphone
select `contact`.identifier, ifnull(user.attribute, `contact`.attribute), ifnull(user.value,`contact`.value)
from contact `contact`
  join contact `active`
    on `contact`.collection = 'contact' and `contact`.attribute in ('time_added','cellphone','contact_info','access_level','owner')
    and  `active`.collection = `contact`.collection and `contact`.identifier = `active`.identifier
    and `active`.attribute = 'active' and `active`.value = 1
  join contact `partner`
    on  `partner`.collection = `contact`.collection and `contact`.identifier = `partner`.identifier
    and `partner`.attribute = 'partner' and `partner`.value = 526
  left join auth `user`
      on `user`.collection = 'user' and `user`.attribute in ('email','first_name')
      and `contact`.collection = 'contact' and `contact`.attribute = 'owner'
      and `user`.identifier = `contact`.value
  left join contact sorter
      on `contact`.collection = sorter.collection and `contact`.identifier = sorter.identifier and sorter.attribute = 'cellphone'
  order by sorter.value, 1
-- 0.1080 seconds

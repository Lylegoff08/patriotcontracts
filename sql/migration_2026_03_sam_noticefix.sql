-- SAM noticedesc and long-description safety
ALTER TABLE contracts_clean
  MODIFY COLUMN description MEDIUMTEXT NULL;
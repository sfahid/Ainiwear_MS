USE aini_wear;
INSERT INTO component_types(name) VALUES ('Embroidery'),('Rubber'),('Woven'),('Silicone'),('Buttons'),('Labels'),('Logo'),('Custom');
INSERT INTO step_templates(name,position) VALUES
('Color finalization',10),('Client review',20),('Client confirmation',30),('Knitting',40),('Logo preparation',50),('Sublimation',60),('Laser work',70),('Cutting',80),('Sewing',90),('Washing',100),('Quality check',110),('Packing',120);
INSERT INTO customers(name,email,phone,country,address) VALUES ('Sample Sports Club','buyer@example.com','+44 000 000 000','United Kingdom','Example address — replace before use');
INSERT INTO orders(code,customer_id,status,order_date,due_date,ship_date,priority,destination,tracking,details)
VALUES('AW-DEMO-001',LAST_INSERT_ID(),'confirmed',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 14 DAY),DATE_ADD(CURDATE(),INTERVAL 16 DAY),'normal','United Kingdom','','Demo order: 100 sublimated jerseys. Confirm colors and artwork with the buyer.');
INSERT INTO items(order_id,name,sku,quantity,fabric,colors,sizes,requirements,due_date)
VALUES(LAST_INSERT_ID(),'Team jersey','JER-001',100,'Polyester 150 GSM','Navy / white','S:20, M:30, L:30, XL:20','Sublimation. Woven label. Embroidered chest logo 8 cm. Client must approve artwork.',DATE_ADD(CURDATE(),INTERVAL 12 DAY));
INSERT INTO components(item_id,type_id,name,quantity,details,status,supplier,due_date)
VALUES(1,1,'Chest crest',100,'8 cm, left chest. Match approved artwork.','pending','',DATE_ADD(CURDATE(),INTERVAL 5 DAY)),(1,6,'Care labels',100,'Polyester care instructions','pending','',DATE_ADD(CURDATE(),INTERVAL 5 DAY));
INSERT INTO item_steps(item_id,name,position,status,notes,due_date) VALUES
(1,'Color finalization',10,'done','Navy approved',CURDATE()),(1,'Client confirmation',30,'pending','Await artwork approval',DATE_ADD(CURDATE(),INTERVAL 2 DAY)),(1,'Sublimation',60,'pending','',DATE_ADD(CURDATE(),INTERVAL 6 DAY)),(1,'Cutting',80,'pending','',DATE_ADD(CURDATE(),INTERVAL 7 DAY)),(1,'Sewing',90,'pending','',DATE_ADD(CURDATE(),INTERVAL 10 DAY)),(1,'Quality check',110,'pending','',DATE_ADD(CURDATE(),INTERVAL 12 DAY));
INSERT INTO notes(order_id,body,followup_date) VALUES(1,'Request signed artwork approval from buyer.',DATE_ADD(CURDATE(),INTERVAL 1 DAY));
-- Fictional examples only, not live courier quotes.
INSERT INTO shipping_rates(courier,service,destination,currency,min_kg,max_kg,rate,transit_days,valid_until)
VALUES('Demo Courier','Economy','United Kingdom','USD',0,5,42,8,DATE_ADD(CURDATE(),INTERVAL 90 DAY)),('Demo Courier','Express','United Kingdom','USD',0,5,65,3,DATE_ADD(CURDATE(),INTERVAL 90 DAY));

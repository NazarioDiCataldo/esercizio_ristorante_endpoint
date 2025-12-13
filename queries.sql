Queries
create table tables (
    number smallint generated always as identity primary key,
    capacity smallint not null check(capacity > 0),
	is_occupied bool not null default false,
	current_guests smallint not null check(current_guests >= 0),
	created_at timestamp default now(),
	updated_at timestamp
)

create table menu_items (
	id smallint generated always as identity primary key,
	name varchar(100) not null,
	description varchar(255) not null default false,
	base_price numeric(6,2) not null check(base_price >= 0),
	category varchar(30) not null,
	volume smallint check(volume >= 10),
	is_alcoholic bool,
	temperature varchar(10),
	allergens varchar(255),
	is_vegetarian bool,
	is_vegan bool,
	is_gluten_free bool,
	preparation_time smallint check(preparation_time >= 0),
	is_sugar_free bool,
	contains_nuts bool,
	item_type varchar(30) not null check(item_type = 'dish' or item_type ='dessert' or item_type='beverage'),
	created_at timestamp default now(),
	updated_at timestamp
);

create table beverages (
	id smallint generated always as identity primary key,
	name varchar(100) not null,
	description varchar(255) not null default false,
	base_price numeric(6,2) not null check(base_price >= 0),
	category varchar(30) not null,
	volume smallint not null,
	is_alcoholic bool not null,
	temperature varchar(10) not null,
	created_at timestamp default now(),
	updated_at timestamp
);
 
create table dishes(
	id smallint generated always as identity primary key,
	name varchar(100) not null,
	description varchar(255) not null default false,
	base_price numeric(6,2) not null check(base_price >= 0),
	category varchar(30) not null,
	allergens varchar(255) not null,
	is_vegetarian bool not null,
	is_vegan bool not null,
	is_gluten_free bool not null,
	preparation_time smallint not null check(preparation_time >= 0),
	created_at timestamp default now(),
	updated_at timestamp
);

create table desserts(
	id smallint generated always as identity primary key,
	name varchar(100) not null,
	description varchar(255) not null default false,
	base_price numeric(6,2) not null check(base_price >= 0),
	category varchar(30) not null,
	is_gluten_free bool not null,
	is_sugar_free bool not null,
	contains_nuts bool not null,
	preparation_time smallint not null check(preparation_time >= 0),
	created_at timestamp default now(),
	updated_at timestamp
);

create table notifications(
	id smallint generated always as identity primary key,
	message text not null,
	type varchar(30) not null,
	timestamp timestamp not null,
	is_read bool not null default false, 
	created_at timestamp default now(),
	updated_at timestamp
);

create table dish_order_items (
	dish_id smallint not null,
	order_item_id smallint not null,
	created_at timestamp default now(),
	updated_at timestamp,
	primary key (dish_id, order_item_id),
	foreign key(dish_id) references dishes(id),
	foreign key(order_item_id) references order_items (id)
); 

create table dessert_order_items (
	dessert_id smallint not null,
	order_item_id smallint not null,
	created_at timestamp default now(),
	updated_at timestamp,
	primary key (dessert_id, order_item_id),
	foreign key(dessert_id) references desserts(id),
	foreign key(order_item_id) references order_items (id)
);

create table order_items(
	id smallint generated always as identity primary key,
	menu_item_id smallint not null,
	quantity smallint not null check(quantity > 0),
	customizations text not null, 
	created_at timestamp default now(),
	updated_at timestamp,
	foreign key(menu_item_id) references menu_items(id)
);

create table orders(
	id smallint generated always as identity primary key,
	table_id smallint not null,
	state varchar(20) not null,
	cover_charge numeric(4,2) not null check(cover_charge >= 0),
	service_charge numeric(4,2) not null check(service_charge >= 0),
	tip numeric(4,2) not null check(tip >= 0),
	created_at timestamp default now(),
	updated_at timestamp,
	foreign key(table_id) references tables(number)
); 

create table orders_tables (
	id smallserial,
	order_id smallint not null,
	table_id smallint not null,
	primary key(id),
	foreign key(order_id) references orders(id),
	foreign key(table_id) references orders(id)
)

/* DML */

INSERT INTO tables (capacity, is_occupied, current_guests) VALUES
(2, false, 0),
(4, false, 0),
(6, true, 4),
(2, true, 2),
(8, false, 0),
(4, true, 3),
(2, false, 0),
(10, false, 0),
(6, true, 5),
(4, false, 0);

INSERT INTO beverages 
(name, description, base_price, category, volume, is_alcoholic, temperature, menu_item_id) VALUES
('Coca Cola', 'Bibita gassata', 2.50, 'Soft Drink', 330, false, 'cold', 1),
('Acqua Naturale', 'Acqua in bottiglia', 1.20, 'Water', 500, false, 'cold', 2),
('Acqua Frizzante', 'Acqua gassata', 1.30, 'Water', 500, false, 'cold', 3),
('Birra Lager', 'Birra chiara', 4.00, 'Beer', 400, true, 'cold', 4),
('Birra IPA', 'Birra aromatica', 4.50, 'Beer', 400, true, 'cold', 5),
('Vino Rosso', 'Vino da tavola', 5.00, 'Wine', 150, true, 'ambient', 6),
('Vino Bianco', 'Fresco e leggero', 5.00, 'Wine', 150, true, 'cold', 7),
('Caffè Espresso', 'Classico espresso', 1.00, 'Coffee', 30, false, 'hot', 8),
('Cappuccino', 'Con latte schiumato', 1.80, 'Coffee', 200, false, 'hot', 9),
('Tè Freddo', 'Tè al limone', 2.00, 'Tea', 330, false, 'cold', 10);


INSERT INTO dishes
(name, description, base_price, category, allergens, is_vegetarian, is_vegan, is_gluten_free, preparation_time, menu_item_id) VALUES
('Spaghetti al Pomodoro', 'Piatto tradizionale italiano', 7.00, 'Primo', 'glutine', true, true, false, 10, 11),
('Lasagne', 'Ricetta al ragù', 9.50, 'Primo', 'glutine, latticini', false, false, false, 20, 12),
('Risotto ai Funghi', 'Risotto con funghi porcini', 8.50, 'Primo', 'latticini', true, false, true, 18, 13),
('Insalata Mista', 'Verdure fresche', 5.00, 'Antipasto', '', true, true, true, 5, 14),
('Bistecca', 'Taglio di manzo', 14.00, 'Secondo', '', false, false, true, 15, 15),
('Pollo alla Griglia', 'Petto di pollo', 10.00, 'Secondo', '', false, false, true, 12, 16),
('Zuppa di Verdure', 'Zuppa calda', 6.00, 'Primo', '', true, true, true, 8, 17),
('Tagliere Salumi', 'Misto salumi', 8.00, 'Antipasto', '', false, false, true, 5, 18),
('Penne al Pesto', 'Pasta con pesto', 8.00, 'Primo', 'glutine, frutta a guscio', true, false, false, 12, 19),
('Hamburger', 'Carne e pane', 9.00, 'Secondo', 'glutine', false, false, false, 10, 20);

INSERT INTO desserts
(name, description, base_price, category, is_gluten_free, is_sugar_free, contains_nuts, preparation_time, menu_item_id) VALUES
('Tiramisù', 'Dolce al caffè', 4.50, 'Dolce', false, false, false, 15, 21),
('Panna Cotta', 'Classico italiano', 4.00, 'Dolce', true, false, false, 8, 22),
('Cheesecake', 'Alla fragola', 4.50, 'Dolce', false, false, false, 20, 23),
('Mousse al Cioccolato', 'Soffice e cremosa', 4.00, 'Dolce', true, false, false, 10, 24),
('Gelato Vaniglia', 'Artigianale', 3.00, 'Dolce', true, false, false, 5, 25),
('Torta alle Noci', 'Con noci tritate', 4.50, 'Dolce', false, false, true, 15, 26),
('Brownie', 'Al cioccolato', 3.50, 'Dolce', false, false, true, 12, 27),
('Torta Vegana', 'Ingredienti vegetali', 4.00, 'Dolce', true, true, false, 20, 28),
('Sorbetto al Limone', 'Rinfrescante', 3.00, 'Dolce', true, true, false, 5, 29),
('Crème Brûlée', 'Super classico', 4.50, 'Dolce', true, false, false, 20, 30);



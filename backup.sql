--
-- PostgreSQL database dump
--

\restrict KAteYA8IXp0mr3ZB84ss66fui3RXwT8f9SlhwbHYbbxyblC7FvgpO20LkE52IVG

-- Dumped from database version 16.15 (Debian 16.15-1.pgdg13+2)
-- Dumped by pg_dump version 16.15 (Debian 16.15-1.pgdg13+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: russian_hunspell; Type: TEXT SEARCH DICTIONARY; Schema: public; Owner: -
--

CREATE TEXT SEARCH DICTIONARY public.russian_hunspell (
    TEMPLATE = pg_catalog.ispell,
    dictfile = 'ru_ru', afffile = 'ru_ru', stopwords = 'russian' );


--
-- Name: russian_hunspell; Type: TEXT SEARCH CONFIGURATION; Schema: public; Owner: -
--

CREATE TEXT SEARCH CONFIGURATION public.russian_hunspell (
    PARSER = pg_catalog."default" );

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR asciiword WITH english_stem;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR word WITH public.russian_hunspell, russian_stem;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR numword WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR email WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR url WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR host WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR sfloat WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR version WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR hword_numpart WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR hword_part WITH public.russian_hunspell, russian_stem;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR hword_asciipart WITH english_stem;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR numhword WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR asciihword WITH english_stem;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR hword WITH public.russian_hunspell, russian_stem;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR url_path WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR file WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR "float" WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR "int" WITH simple;

ALTER TEXT SEARCH CONFIGURATION public.russian_hunspell
    ADD MAPPING FOR uint WITH simple;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: code; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.code (
    id bigint NOT NULL,
    code text NOT NULL,
    item_id bigint,
    store_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT code_xor_check CHECK (((store_id IS NULL) OR (item_id IS NULL)))
);


--
-- Name: code_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.code_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: code_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.code_id_seq OWNED BY public.code.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: file; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.file (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: file_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.file_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: file_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.file_id_seq OWNED BY public.file.id;


--
-- Name: font; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.font (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    key character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: font_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.font_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: font_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.font_id_seq OWNED BY public.font.id;


--
-- Name: image; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.image (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: image_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.image_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: image_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.image_id_seq OWNED BY public.image.id;


--
-- Name: image_m2m_item; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.image_m2m_item (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: image_m2m_item_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.image_m2m_item_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: image_m2m_item_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.image_m2m_item_id_seq OWNED BY public.image_m2m_item.id;


--
-- Name: image_m2m_label_preset; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.image_m2m_label_preset (
    id bigint NOT NULL,
    image_id bigint NOT NULL,
    label_preset_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: image_m2m_label_preset_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.image_m2m_label_preset_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: image_m2m_label_preset_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.image_m2m_label_preset_id_seq OWNED BY public.image_m2m_label_preset.id;


--
-- Name: image_m2m_store; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.image_m2m_store (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: image_m2m_store_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.image_m2m_store_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: image_m2m_store_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.image_m2m_store_id_seq OWNED BY public.image_m2m_store.id;


--
-- Name: item; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.item (
    id bigint NOT NULL,
    title text NOT NULL,
    title_print text,
    store_id bigint,
    quantity bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    search_vector tsvector GENERATED ALWAYS AS ((setweight(to_tsvector('public.russian_hunspell'::regconfig, COALESCE(title, ''::text)), 'A'::"char") || setweight(to_tsvector('public.russian_hunspell'::regconfig, COALESCE(title_print, ''::text)), 'B'::"char"))) STORED NOT NULL
);


--
-- Name: item_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.item_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: item_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.item_id_seq OWNED BY public.item.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: label_list; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label_list (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    label_preset_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    search_vector tsvector GENERATED ALWAYS AS (setweight(to_tsvector('public.russian_hunspell'::regconfig, (COALESCE(title, (''::text)::character varying))::text), 'A'::"char")) STORED NOT NULL
);


--
-- Name: label_list_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.label_list_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: label_list_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.label_list_id_seq OWNED BY public.label_list.id;


--
-- Name: label_list_m2m_item; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label_list_m2m_item (
    id bigint NOT NULL,
    label_list_id bigint NOT NULL,
    item_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: label_list_m2m_item_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.label_list_m2m_item_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: label_list_m2m_item_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.label_list_m2m_item_id_seq OWNED BY public.label_list_m2m_item.id;


--
-- Name: label_list_m2m_store; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label_list_m2m_store (
    id bigint NOT NULL,
    label_list_id bigint NOT NULL,
    store_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: label_list_m2m_store_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.label_list_m2m_store_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: label_list_m2m_store_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.label_list_m2m_store_id_seq OWNED BY public.label_list_m2m_store.id;


--
-- Name: label_preset; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label_preset (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    page_width double precision DEFAULT '210'::double precision NOT NULL,
    page_height double precision DEFAULT '297'::double precision NOT NULL,
    page_margin_top double precision DEFAULT '10'::double precision NOT NULL,
    page_margin_right double precision DEFAULT '10'::double precision NOT NULL,
    page_margin_bottom double precision DEFAULT '10'::double precision NOT NULL,
    page_margin_left double precision DEFAULT '10'::double precision NOT NULL,
    cell_width double precision DEFAULT '78'::double precision NOT NULL,
    cell_height double precision DEFAULT '23'::double precision NOT NULL,
    cell_pad_top double precision DEFAULT '3'::double precision NOT NULL,
    cell_pad_right double precision DEFAULT '5'::double precision NOT NULL,
    cell_pad_bottom double precision DEFAULT '5'::double precision NOT NULL,
    cell_pad_left double precision DEFAULT '5'::double precision NOT NULL,
    barcode_position character varying(255) DEFAULT 'left'::character varying NOT NULL,
    barcode_text_gap double precision DEFAULT '2'::double precision NOT NULL,
    barcode_size double precision DEFAULT '13'::double precision NOT NULL,
    font_id bigint,
    font_size_min double precision DEFAULT '5'::double precision NOT NULL,
    font_size_max double precision DEFAULT '24'::double precision NOT NULL,
    font_size_step double precision DEFAULT '0.5'::double precision NOT NULL,
    line_height_factor double precision DEFAULT '1.25'::double precision NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: label_preset_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.label_preset_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: label_preset_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.label_preset_id_seq OWNED BY public.label_preset.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: store; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.store (
    id bigint NOT NULL,
    title text NOT NULL,
    title_print text,
    parent_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    search_vector tsvector GENERATED ALWAYS AS ((setweight(to_tsvector('public.russian_hunspell'::regconfig, COALESCE(title, ''::text)), 'A'::"char") || setweight(to_tsvector('public.russian_hunspell'::regconfig, COALESCE(title_print, ''::text)), 'B'::"char"))) STORED NOT NULL
);


--
-- Name: store_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.store_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: store_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.store_id_seq OWNED BY public.store.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: code id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.code ALTER COLUMN id SET DEFAULT nextval('public.code_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: file id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.file ALTER COLUMN id SET DEFAULT nextval('public.file_id_seq'::regclass);


--
-- Name: font id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.font ALTER COLUMN id SET DEFAULT nextval('public.font_id_seq'::regclass);


--
-- Name: image id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image ALTER COLUMN id SET DEFAULT nextval('public.image_id_seq'::regclass);


--
-- Name: image_m2m_item id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_item ALTER COLUMN id SET DEFAULT nextval('public.image_m2m_item_id_seq'::regclass);


--
-- Name: image_m2m_label_preset id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_label_preset ALTER COLUMN id SET DEFAULT nextval('public.image_m2m_label_preset_id_seq'::regclass);


--
-- Name: image_m2m_store id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_store ALTER COLUMN id SET DEFAULT nextval('public.image_m2m_store_id_seq'::regclass);


--
-- Name: item id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item ALTER COLUMN id SET DEFAULT nextval('public.item_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: label_list id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list ALTER COLUMN id SET DEFAULT nextval('public.label_list_id_seq'::regclass);


--
-- Name: label_list_m2m_item id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_item ALTER COLUMN id SET DEFAULT nextval('public.label_list_m2m_item_id_seq'::regclass);


--
-- Name: label_list_m2m_store id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_store ALTER COLUMN id SET DEFAULT nextval('public.label_list_m2m_store_id_seq'::regclass);


--
-- Name: label_preset id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_preset ALTER COLUMN id SET DEFAULT nextval('public.label_preset_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: store id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.store ALTER COLUMN id SET DEFAULT nextval('public.store_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: code; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.code (id, code, item_id, store_id, created_at, updated_at) FROM stdin;
1	019b76da-a801-7754-8cdc-2be88654abc1	\N	1	\N	2026-09-14 16:25:44
2	019b76da-a802-71ff-a51c-c6d5f01fb27c	\N	2	\N	2026-09-14 16:25:44
3	019b76da-a803-700f-aaa5-49a71bde61d1	\N	3	\N	2026-09-14 16:25:44
4	019b76da-a804-7669-efa6-0302a5a2ae71	\N	4	\N	2026-09-14 16:25:44
5	019b76da-a805-74d0-cbea-5d4fc8372fb6	\N	5	\N	2026-09-14 16:25:44
6	019b76da-a806-773e-c544-76a0667a53b2	\N	6	\N	2026-09-14 16:25:44
7	019b76da-a807-7aa6-a47b-dafda9c1acc6	\N	7	\N	2026-09-14 16:25:44
8	019b76da-a808-7a78-db5a-fa3480191756	\N	8	\N	2026-09-14 16:25:44
9	019b76da-a809-7fa9-e875-f74422563838	\N	9	\N	2026-09-14 16:25:44
10	019b76da-a80a-74a0-a41e-fabaa5156ddf	\N	10	\N	2026-09-14 16:25:44
11	019b76da-a80b-7a20-f516-6faa6aadcbb4	\N	11	\N	2026-09-14 16:25:44
12	019b76da-a80c-7c31-ab8a-11dc98745988	\N	12	\N	2026-09-14 16:25:44
13	019b76da-a80d-7dbe-cd21-6a1134a804da	\N	13	\N	2026-09-14 16:25:44
14	019b76da-a80e-7dd9-b31d-dfea2eb309be	\N	14	\N	2026-09-14 16:25:44
15	019b76da-a80f-74cd-a398-670583ac0e2a	\N	15	\N	2026-09-14 16:25:44
16	019b76da-a810-7b59-992e-6b4d5ed16de8	\N	16	\N	2026-09-14 16:25:44
17	019b76da-a811-7583-f2bc-291a3d72b00a	\N	17	\N	2026-09-14 16:25:44
18	019b76da-a812-703c-cf64-269b5adb1948	\N	18	\N	2026-09-14 16:25:44
19	019b76da-a813-799b-84da-645e4af57dd3	1	\N	\N	2026-09-14 16:25:44
20	019b76da-a814-713d-b55d-c6fdb39c6b15	2	\N	\N	2026-09-14 16:25:44
21	019b76da-a815-722f-99aa-5f38fc98e153	3	\N	\N	2026-09-14 16:25:44
22	019b76da-a816-7689-ba12-8deb2cc8ab25	4	\N	\N	2026-09-14 16:25:44
23	019b76da-a817-7e84-bf0d-66d6297bc54f	5	\N	\N	2026-09-14 16:25:44
24	019b76da-a818-7db5-daf0-69d26010feb8	6	\N	\N	2026-09-14 16:25:44
25	019b76da-a819-7c42-dc38-964d3dd44cab	7	\N	\N	2026-09-14 16:25:44
26	019b76da-a81a-7358-ff32-537ffa358f31	8	\N	\N	2026-09-14 16:25:44
27	019b76da-a81b-78dd-b150-768adf5b6281	9	\N	\N	2026-09-14 16:25:44
28	019b76da-a81c-7f08-8f47-f2ef6ca74c9e	10	\N	\N	2026-09-14 16:25:44
29	019b76da-a81d-7067-ddac-a6db59247cae	11	\N	\N	2026-09-14 16:25:44
30	019b76da-a81e-78ba-860c-1ad0276181c7	12	\N	\N	2026-09-14 16:25:44
31	019b76da-a81f-7d27-c303-dfb64cc69f22	13	\N	\N	2026-09-14 16:25:44
32	019b76da-a820-7599-cc9d-6929c887c7a5	14	\N	\N	2026-09-14 16:25:44
33	019b76da-a821-7c5a-d1bc-54208d753206	15	\N	\N	2026-09-14 16:25:44
34	019b76da-a822-76f6-8308-4d7f857ad2b1	16	\N	\N	2026-09-14 16:25:44
35	019b76da-a823-7f3d-a0f9-5faedf7e6782	17	\N	\N	2026-09-14 16:25:44
36	019b76da-a824-7076-d173-bc0857df1b82	18	\N	\N	2026-09-14 16:25:44
37	019b76da-a825-7068-9eb1-b030a7bf77fb	19	\N	\N	2026-09-14 16:25:44
38	019b76da-a826-7eff-9a3f-9f3827b6b618	20	\N	\N	2026-09-14 16:25:44
39	019b76da-a827-7053-d2df-0f489b1a64ce	21	\N	\N	2026-09-14 16:25:44
40	019b76da-a828-74e3-fc01-bfae2777d904	22	\N	\N	2026-09-14 16:25:44
41	019b76da-a829-75e8-b82f-8d8362956fb7	23	\N	\N	2026-09-14 16:25:44
42	019b76da-a82a-7c65-a579-4501722a7192	24	\N	\N	2026-09-14 16:25:44
43	019b76da-a82b-78af-b82b-dca89fe81084	25	\N	\N	2026-09-14 16:25:44
44	019b76da-a82c-7390-ac97-8edf51034559	26	\N	\N	2026-09-14 16:25:44
45	019b76da-a82d-7773-e12d-33525ff4120b	27	\N	\N	2026-09-14 16:25:44
46	019b76da-a82e-7089-d3f5-38413e54e0f1	28	\N	\N	2026-09-14 16:25:44
47	019b76da-a82f-71ae-9486-b1c15e9ad518	29	\N	\N	2026-09-14 16:25:44
48	019b76da-a830-78c9-c7de-b63d861c5d2e	30	\N	\N	2026-09-14 16:25:44
49	019b76da-a831-75fa-8797-2bea68f9d812	31	\N	\N	2026-09-14 16:25:44
50	019b76da-a832-779d-f47d-4d456e3c6e8d	32	\N	\N	2026-09-14 16:25:44
51	019b76da-a833-7486-f521-923008b40c09	33	\N	\N	2026-09-14 16:25:44
52	019b76da-a834-727e-d331-20ee15108a62	34	\N	\N	2026-09-14 16:25:44
53	019b76da-a835-7fa8-d90c-a86224b83403	35	\N	\N	2026-09-14 16:25:44
54	019b76da-a836-7d50-a0ea-b04d536d5725	36	\N	\N	2026-09-14 16:25:44
55	019b76da-a837-777a-9ae2-28d4a7882ea6	37	\N	\N	2026-09-14 16:25:44
56	019b76da-a838-7dfb-fd1f-d358504af3ca	38	\N	\N	2026-09-14 16:25:44
57	019b76da-a839-7940-b422-ab228d252c2d	39	\N	\N	2026-09-14 16:25:44
58	019b76da-a83a-7099-b125-982894ebeaf8	40	\N	\N	2026-09-14 16:25:44
59	019b76da-a83b-754e-f267-8a84927d06fb	41	\N	\N	2026-09-14 16:25:44
60	019b76da-a83c-7444-dfd5-e04ae2a3a6ae	42	\N	\N	2026-09-14 16:25:44
61	019b76da-a83d-75af-ece1-4d9ff8863425	43	\N	\N	2026-09-14 16:25:44
62	019b76da-a83e-7186-feab-52ec9fc6a7db	44	\N	\N	2026-09-14 16:25:44
63	019b76da-a83f-728e-d362-7efd767b8d80	45	\N	\N	2026-09-14 16:25:44
64	019b76da-a840-71db-eaf1-833ab8aab594	46	\N	\N	2026-09-14 16:25:44
65	019b76da-a841-7b85-e876-71bb34107720	47	\N	\N	2026-09-14 16:25:44
66	019b76da-a842-76e2-a3d5-572b99c94ccf	48	\N	\N	2026-09-14 16:25:44
67	019b76da-a843-7a13-c084-477752d10664	49	\N	\N	2026-09-14 16:25:44
68	019b76da-a844-73c6-a09a-22712e936829	50	\N	\N	2026-09-14 16:25:44
69	019b76da-a845-7510-df86-b9ad01fdafbe	51	\N	\N	2026-09-14 16:25:44
70	019b76da-a846-7dff-8970-0463872ec9bd	52	\N	\N	2026-09-14 16:25:44
71	019b76da-a847-7be6-cc4d-532136d875cd	53	\N	\N	2026-09-14 16:25:44
72	019b76da-a848-7790-8916-3c1931e6c0fc	54	\N	\N	2026-09-14 16:25:44
73	019b76da-a849-7f14-a54d-e77bfbb825c1	55	\N	\N	2026-09-14 16:25:44
74	019b76da-a84a-76b5-d10c-3f7b92d0cbff	56	\N	\N	2026-09-14 16:25:44
75	019b76da-a84b-7a34-e1c7-841b9a3db457	57	\N	\N	2026-09-14 16:25:44
76	019b76da-a84c-7b90-f193-9cadbc4cf9fe	58	\N	\N	2026-09-14 16:25:44
77	019b76da-a84d-7d34-e234-6d436e3f76a7	59	\N	\N	2026-09-14 16:25:44
78	019b76da-a84e-7dfa-d80d-62e4bfd5cea0	60	\N	\N	2026-09-14 16:25:44
79	019b76da-a84f-7b64-9036-1a02d5042594	61	\N	\N	2026-09-14 16:25:44
80	019b76da-a850-72ea-e7c3-315a7239f89f	62	\N	\N	2026-09-14 16:25:44
81	019b76da-a851-7bcd-ad5b-9552a8077c0c	63	\N	\N	2026-09-14 16:25:44
82	019b76da-a852-749a-f771-d0f6b2af5ea3	64	\N	\N	2026-09-14 16:25:44
83	019b76da-a853-7ad6-b611-ec8e01f72973	65	\N	\N	2026-09-14 16:25:44
84	019b76da-a854-7050-edc0-5bd8b59b594c	66	\N	\N	2026-09-14 16:25:44
85	019b76da-a855-727b-e546-5d217e7918cc	67	\N	\N	2026-09-14 16:25:44
86	019b76da-a856-7b47-9b7d-252e911bc28a	68	\N	\N	2026-09-14 16:25:44
87	019b76da-a857-79a8-81c8-ca7da8d076b7	69	\N	\N	2026-09-14 16:25:44
88	019b76da-a858-74fc-9621-a23c5709dc1c	70	\N	\N	2026-09-14 16:25:44
89	019b76da-a859-76fc-81d9-96cf75fa452a	71	\N	\N	2026-09-14 16:25:44
90	019b76da-a85a-7955-ddd4-0b269cf2348e	72	\N	\N	2026-09-14 16:25:44
91	019b76da-a85b-7d8c-e965-93b634700c3a	73	\N	\N	2026-09-14 16:25:44
92	019b76da-a85c-7b52-86ca-590d83017852	74	\N	\N	2026-09-14 16:25:44
93	019b76da-a85d-7f50-a979-58d1b0b86873	75	\N	\N	2026-09-14 16:25:44
94	019b76da-a85e-767b-f8ab-b0a0764d2bb0	76	\N	\N	2026-09-14 16:25:44
95	019b76da-a85f-7d6c-8e30-7dd24ab2e953	77	\N	\N	2026-09-14 16:25:44
96	019b76da-a860-7a9f-c63f-84731288d04c	78	\N	\N	2026-09-14 16:25:44
97	019b76da-a861-7082-a3fa-00ebffeb35f5	79	\N	\N	2026-09-14 16:25:44
98	019b76da-a862-74b2-8c71-04c10225d8f1	80	\N	\N	2026-09-14 16:25:44
99	019b76da-a863-7344-cefb-398eaef73b05	81	\N	\N	2026-09-14 16:25:44
100	019b76da-a864-7ce6-ba7c-061fcac7852f	82	\N	\N	2026-09-14 16:25:44
101	019b76da-a865-7caa-8e20-79634fa0d6dd	83	\N	\N	2026-09-14 16:25:44
102	019b76da-a866-7042-fcde-a3ce4581b5cc	84	\N	\N	2026-09-14 16:25:44
103	019b76da-a867-7023-c469-e9c7163ebe4d	85	\N	\N	2026-09-14 16:25:44
104	019b76da-a868-7cf1-abf3-a075f3dab87e	86	\N	\N	2026-09-14 16:25:44
105	019b76da-a869-73ff-ef12-3556861730a8	87	\N	\N	2026-09-14 16:25:44
106	019b76da-a86a-7c14-8e84-119d9842e2a3	88	\N	\N	2026-09-14 16:25:44
107	019b76da-a86b-7787-a141-7c9982ccf0c9	89	\N	\N	2026-09-14 16:25:44
108	019b76da-a86c-758c-ea2d-f92336602e15	90	\N	\N	2026-09-14 16:25:44
109	019b76da-a86d-7e11-f4b8-0d03296e9d52	91	\N	\N	2026-09-14 16:25:44
110	019b76da-a86e-7ac4-df55-7a827f4b38f0	92	\N	\N	2026-09-14 16:25:44
111	019b76da-a86f-791b-d01d-6cd4b6385737	93	\N	\N	2026-09-14 16:25:44
112	019b76da-a870-783c-f870-05a770e1575c	94	\N	\N	2026-09-14 16:25:44
113	019b76da-a871-7521-94b1-df8606506f52	95	\N	\N	2026-09-14 16:25:44
114	019b76da-a872-78d1-d755-cf2131142aad	96	\N	\N	2026-09-14 16:25:44
115	019b76da-a873-7c5b-f78d-0c37a8fa6948	97	\N	\N	2026-09-14 16:25:44
116	019b76da-a874-713e-cc50-e962125260d2	98	\N	\N	2026-09-14 16:25:44
117	019b76da-a875-75cc-ed72-24ae4fafd066	99	\N	\N	2026-09-14 16:25:44
118	019b76da-a876-7037-ea07-ef7fee93a3c4	100	\N	\N	2026-09-14 16:25:44
119	4607110273735	101	\N	\N	2026-09-14 16:25:44
120	ii9256223200	102	\N	\N	2026-09-14 16:25:44
121	47457589	103	\N	\N	2026-09-14 16:25:44
122	01a09c0e-f991-73ec-8873-176018f4fad4	\N	19	\N	2026-09-14 16:25:44
123	01a09c0f-4867-72d8-bb21-9ded8f33a7d7	\N	20	\N	2026-09-14 16:25:44
124	01a09c18-516c-72b3-a6e3-52b3ae91a413	\N	21	\N	2026-09-14 16:25:44
126	01a09c1e-9be9-72a5-8e20-352f37cad9eb	\N	23	\N	2026-09-14 16:25:44
127	01a09c1e-add0-7099-9581-76c076ab6212	\N	24	\N	2026-09-14 16:25:44
128	01a09c1f-2096-72f1-8244-da8fc7bd35f6	\N	25	\N	2026-09-14 16:25:44
129	01a09c1f-7183-7065-8685-1084cf3155c4	\N	26	\N	2026-09-14 16:25:44
131	01a0a10e-115e-70ff-81ac-ba097cac3e3d	\N	28	2026-09-14 17:54:08	2026-09-14 17:54:08
132	01a0a10e-2994-7357-983b-720408a2eccb	\N	29	2026-09-14 17:54:15	2026-09-14 17:54:15
133	01a0a10e-f1d8-7363-be1b-52c53d0fe0c4	\N	30	2026-09-14 17:55:06	2026-09-14 17:55:06
135	01a0a14f-d484-7012-bf82-78c05e9aea22	\N	32	2026-09-14 19:05:58	2026-09-14 19:05:58
136	01a0a14f-ef38-725b-8305-7388bc0daae9	\N	33	2026-09-14 19:06:05	2026-09-14 19:06:05
137	01a0a150-01f3-7225-8c13-a759ffc1280c	\N	34	2026-09-14 19:06:10	2026-09-14 19:06:10
138	01a0a150-0e7c-72af-99fe-701ad8a29a77	\N	35	2026-09-14 19:06:13	2026-09-14 19:06:13
139	01a0a150-19db-72c5-9021-be603496419e	\N	36	2026-09-14 19:06:16	2026-09-14 19:06:16
140	01a0a150-25b0-7069-8912-c1d066788717	\N	37	2026-09-14 19:06:19	2026-09-14 19:06:19
141	01a0a150-359a-7335-bb95-014504a50ac1	\N	38	2026-09-14 19:06:23	2026-09-14 19:06:23
142	01a0a150-52bf-71e2-8f40-e261ebf1908a	\N	39	2026-09-14 19:06:30	2026-09-14 19:06:30
143	01a0a151-8666-70a9-939b-8c4fb4ee4378	\N	40	2026-09-14 19:07:49	2026-09-14 19:07:49
144	01a0a151-902c-7007-a436-257f607dc39a	\N	41	2026-09-14 19:07:52	2026-09-14 19:07:52
145	01a0a151-9b2e-7155-90ea-54e3a7b6edb3	\N	42	2026-09-14 19:07:55	2026-09-14 19:07:55
146	01a0a151-a46c-73ba-ab91-2b2d1b544054	\N	43	2026-09-14 19:07:57	2026-09-14 19:07:57
147	01a0a151-ffc0-7306-8140-68bd25f16dcd	\N	44	2026-09-14 19:08:20	2026-09-14 19:08:20
148	01a0a152-4a9b-7138-a818-dfadf650f6ea	\N	45	2026-09-14 19:08:39	2026-09-14 19:08:39
149	01a0a153-5516-7271-aa94-cd20159642e2	\N	46	2026-09-14 19:09:48	2026-09-14 19:09:48
150	01a0a153-64da-73dc-a48c-fc041e50d5cc	\N	47	2026-09-14 19:09:52	2026-09-14 19:09:52
151	01a0a153-713c-7067-b412-8b9303a3bbd6	\N	48	2026-09-14 19:09:55	2026-09-14 19:09:55
152	01a0a153-84a0-7260-a112-d3bd8063208f	\N	49	2026-09-14 19:10:00	2026-09-14 19:10:00
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: file; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.file (id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: font; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.font (id, name, key, created_at, updated_at) FROM stdin;
1	Courier	courier	\N	2026-09-14 16:25:44
2	Courier Bold	courierb	\N	2026-09-14 16:25:44
3	Courier Bold Italic	courierbi	\N	2026-09-14 16:25:44
4	Courier Italic	courieri	\N	2026-09-14 16:25:44
5	Helvetica	helvetica	\N	2026-09-14 16:25:44
6	Helvetica Bold	helveticab	\N	2026-09-14 16:25:44
7	Helvetica Bold Italic	helveticabi	\N	2026-09-14 16:25:44
8	Helvetica Italic	helveticai	\N	2026-09-14 16:25:44
9	Symbol	symbol	\N	2026-09-14 16:25:44
10	Times	times	\N	2026-09-14 16:25:44
11	Times Bold	timesb	\N	2026-09-14 16:25:44
12	Times Bold Italic	timesbi	\N	2026-09-14 16:25:44
13	Times Italic	timesi	\N	2026-09-14 16:25:44
14	Zapf Dingbats	zapfdingbats	\N	2026-09-14 16:25:44
15	CID0 CS	cid0cs	\N	2026-09-14 16:25:44
16	CID0 CT	cid0ct	\N	2026-09-14 16:25:44
17	CID0 JP	cid0jp	\N	2026-09-14 16:25:44
18	CID0 KR	cid0kr	\N	2026-09-14 16:25:44
19	PDFA Courier	pdfacourier	\N	2026-09-14 16:25:44
20	PDFA Courier Bold	pdfacourierb	\N	2026-09-14 16:25:44
21	PDFA Courier Bold Italic	pdfacourierbi	\N	2026-09-14 16:25:44
22	PDFA Courier Italic	pdfacourieri	\N	2026-09-14 16:25:44
23	PDFA Helvetica	pdfahelvetica	\N	2026-09-14 16:25:44
24	PDFA Helvetica Bold	pdfahelveticab	\N	2026-09-14 16:25:44
25	PDFA Helvetica Bold Italic	pdfahelveticabi	\N	2026-09-14 16:25:44
26	PDFA Helvetica Italic	pdfahelveticai	\N	2026-09-14 16:25:44
27	PDFA Symbol	pdfasymbol	\N	2026-09-14 16:25:44
28	PDFA Times	pdfatimes	\N	2026-09-14 16:25:44
29	PDFA Times Bold	pdfatimesb	\N	2026-09-14 16:25:44
30	PDFA Times Bold Italic	pdfatimesbi	\N	2026-09-14 16:25:44
31	PDFA Times Italic	pdfatimesi	\N	2026-09-14 16:25:44
32	PDFA Zapf Dingbats	pdfazapfdingbats	\N	2026-09-14 16:25:44
33	DejaVu Math TeX Gyre	dejavumathtexgyre	\N	2026-09-14 16:25:44
34	DejaVu Sans	dejavusans	\N	2026-09-14 16:25:44
35	DejaVu Sans Bold	dejavusansb	\N	2026-09-14 16:25:44
36	DejaVu Sans Bold Italic	dejavusansbi	\N	2026-09-14 16:25:44
37	DejaVu Sans Condensed	dejavusanscondensed	\N	2026-09-14 16:25:44
38	DejaVu Sans Condensed Bold	dejavusanscondensedb	\N	2026-09-14 16:25:44
39	DejaVu Sans Condensed Bold Italic	dejavusanscondensedbi	\N	2026-09-14 16:25:44
40	DejaVu Sans Condensed Italic	dejavusanscondensedi	\N	2026-09-14 16:25:44
41	DejaVu Sans Extra Light	dejavusansextralight	\N	2026-09-14 16:25:44
42	DejaVu Sans Italic	dejavusansi	\N	2026-09-14 16:25:44
43	DejaVu Sans Mono	dejavusansmono	\N	2026-09-14 16:25:44
44	DejaVu Sans Mono Bold	dejavusansmonob	\N	2026-09-14 16:25:44
45	DejaVu Sans Mono Bold Italic	dejavusansmonobi	\N	2026-09-14 16:25:44
46	DejaVu Sans Mono Italic	dejavusansmonoi	\N	2026-09-14 16:25:44
47	DejaVu Serif	dejavuserif	\N	2026-09-14 16:25:44
48	DejaVu Serif Bold	dejavuserifb	\N	2026-09-14 16:25:44
49	DejaVu Serif Bold Italic	dejavuserifbi	\N	2026-09-14 16:25:44
50	DejaVu Serif Condensed	dejavuserifcondensed	\N	2026-09-14 16:25:44
51	DejaVu Serif Condensed Bold	dejavuserifcondensedb	\N	2026-09-14 16:25:44
52	DejaVu Serif Condensed Bold Italic	dejavuserifcondensedbi	\N	2026-09-14 16:25:44
53	DejaVu Serif Condensed Italic	dejavuserifcondensedi	\N	2026-09-14 16:25:44
54	DejaVu Serif Italic	dejavuserifi	\N	2026-09-14 16:25:44
55	Free Mono	freemono	\N	2026-09-14 16:25:44
56	Free Mono Bold	freemonob	\N	2026-09-14 16:25:44
57	Free Mono Bold Italic	freemonobi	\N	2026-09-14 16:25:44
58	Free Mono Italic	freemonoi	\N	2026-09-14 16:25:44
59	Free Sans	freesans	\N	2026-09-14 16:25:44
60	Free Sans Bold	freesansb	\N	2026-09-14 16:25:44
61	Free Sans Bold Italic	freesansbi	\N	2026-09-14 16:25:44
62	Free Sans Italic	freesansi	\N	2026-09-14 16:25:44
63	Free Serif	freeserif	\N	2026-09-14 16:25:44
64	Free Serif Bold	freeserifb	\N	2026-09-14 16:25:44
65	Free Serif Bold Italic	freeserifbi	\N	2026-09-14 16:25:44
66	Free Serif Italic	freeserifi	\N	2026-09-14 16:25:44
67	Roboto	roboto	\N	2026-09-14 16:25:44
68	Roboto Bold	robotob	\N	2026-09-14 16:25:44
69	Roboto Condensed Bold	robotocondensedb	\N	2026-09-14 16:25:44
70	Ubuntu	ubuntu	\N	2026-09-14 16:25:44
71	Ubuntu Bold	ubuntub	\N	2026-09-14 16:25:44
72	Unifont	unifont	\N	2026-09-14 16:25:44
73	Unifont CSUR	unifont_csur	\N	2026-09-14 16:25:44
74	Unifont JP	unifont_jp	\N	2026-09-14 16:25:44
75	Unifont T	unifont_t	\N	2026-09-14 16:25:44
76	Unifont Upper	unifont_upper	\N	2026-09-14 16:25:44
\.


--
-- Data for Name: image; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.image (id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: image_m2m_item; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.image_m2m_item (id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: image_m2m_label_preset; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.image_m2m_label_preset (id, image_id, label_preset_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: image_m2m_store; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.image_m2m_store (id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: item; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.item (id, title, title_print, store_id, quantity, created_at, updated_at) FROM stdin;
1	Болт М6х20 оцинкованный	\N	4	\N	\N	2026-09-14 16:25:44
2	Болт М8х25 с гайкой и шайбой	\N	5	\N	\N	2026-09-14 16:25:44
3	Болт М10х30 высокопрочный	\N	6	\N	\N	2026-09-14 16:25:44
4	Болт М12х40 с шестигранной головкой	\N	10	\N	\N	2026-09-14 16:25:44
5	Болт М14х50 нержавеющий	\N	11	\N	\N	2026-09-14 16:25:44
6	Болт М16х60 оцинкованный	\N	12	99	\N	2026-09-14 16:25:44
7	Болт М5х16 с потайной головкой	\N	16	\N	\N	2026-09-14 16:25:44
8	Болт фундаментный 12х200	\N	17	\N	\N	2026-09-14 16:25:44
9	Болт анкерный М10х100	\N	18	\N	\N	2026-09-14 16:25:44
10	Болт мебельный М8х40	\N	4	\N	\N	2026-09-14 16:25:44
11	Шуруп 3.5х25 универсальный	\N	5	\N	\N	2026-09-14 16:25:44
12	Шуруп 4х35 оцинкованный	\N	6	\N	\N	2026-09-14 16:25:44
13	Шуруп 5х50 по дереву	\N	10	\N	\N	2026-09-14 16:25:44
14	Шуруп кровельный 4.8х35	\N	11	\N	\N	2026-09-14 16:25:44
15	Шуруп с потайной головкой 3х20	\N	12	\N	\N	2026-09-14 16:25:44
16	Шуруп с прессшайбой 4.2х16	\N	16	\N	\N	2026-09-14 16:25:44
17	Шуруп глухарь 8х120	\N	17	\N	\N	2026-09-14 16:25:44
18	Шуруп гипсокартонный 3.5х25	\N	18	\N	\N	2026-09-14 16:25:44
19	Шуруп с шестигранной головкой 6х60	\N	4	\N	\N	2026-09-14 16:25:44
20	Шуруп нержавеющий 4х40	\N	5	\N	\N	2026-09-14 16:25:44
21	Гайка М6 шестигранная	\N	6	\N	\N	2026-09-14 16:25:44
22	Гайка М8 самоконтрящаяся	\N	10	\N	\N	2026-09-14 16:25:44
23	Гайка М10 с фланцем	\N	11	\N	\N	2026-09-14 16:25:44
24	Гайка М12 колпачковая	\N	12	\N	\N	2026-09-14 16:25:44
25	Гайка М14 прорезная	\N	16	\N	\N	2026-09-14 16:25:44
26	Гайка М16 рым-гайка	\N	17	\N	\N	2026-09-14 16:25:44
27	Гайка М4 барашковая	\N	18	\N	\N	2026-09-14 16:25:44
28	Гайка М5 квадратная	\N	4	\N	\N	2026-09-14 16:25:44
29	Гайка М20 соединительная	\N	5	\N	\N	2026-09-14 16:25:44
30	Гайка М8 фланцевая широкая	\N	6	\N	\N	2026-09-14 16:25:44
31	Шайба плоская М6	\N	10	\N	\N	2026-09-14 16:25:44
32	Шайба гровер М8	\N	11	\N	\N	2026-09-14 16:25:44
33	Шайба пружинная М10	\N	12	\N	\N	2026-09-14 16:25:44
34	Шайба увеличенная М12	\N	16	\N	\N	2026-09-14 16:25:44
35	Шайба стопорная М14	\N	17	\N	\N	2026-09-14 16:25:44
36	Шайба кузовная М8	\N	18	\N	\N	2026-09-14 16:25:44
37	Шайба медная 10мм	\N	4	\N	\N	2026-09-14 16:25:44
38	Шайба термостойкая М16	\N	5	\N	\N	2026-09-14 16:25:44
39	Шайба текстолитовая М20	\N	6	\N	\N	2026-09-14 16:25:44
40	Шайба резиновая уплотнительная 8мм	\N	10	\N	\N	2026-09-14 16:25:44
41	Винт М3х10 с цилиндрической головкой	\N	11	\N	\N	2026-09-14 16:25:44
42	Винт М4х16 с крестообразным шлицем	\N	12	\N	\N	2026-09-14 16:25:44
43	Винт М5х20 установочный	\N	16	\N	\N	2026-09-14 16:25:44
44	Винт М6х25 с внутренним шестигранником	\N	17	\N	\N	2026-09-14 16:25:44
45	Винт М8х30 мебельный конфирмат	\N	18	\N	\N	2026-09-14 16:25:44
46	Винт М2.5х8 с полукруглой головкой	\N	4	\N	\N	2026-09-14 16:25:44
47	Винт М3х12 с потайной головкой	\N	5	\N	\N	2026-09-14 16:25:44
48	Винт стопорный М4х6	\N	6	\N	\N	2026-09-14 16:25:44
49	Винт регулировочный М6х40	\N	10	\N	\N	2026-09-14 16:25:44
50	Винт невыпадающий М3х10	\N	11	\N	\N	2026-09-14 16:25:44
51	Заклёпка вытяжная 3.2х8 алюминий	\N	12	\N	\N	2026-09-14 16:25:44
52	Заклёпка вытяжная 4х10 сталь	\N	16	\N	\N	2026-09-14 16:25:44
53	Заклёпка вытяжная 4.8х12 нерж	\N	17	\N	\N	2026-09-14 16:25:44
54	Заклёпка резьбовая М6	\N	18	\N	\N	2026-09-14 16:25:44
55	Заклёпка под молоток 5х20	\N	4	\N	\N	2026-09-14 16:25:44
56	Заклёпка пистонная 3х6	\N	5	\N	\N	2026-09-14 16:25:44
57	Заклёпка вытяжная 4.8х16 оцинк	\N	6	\N	\N	2026-09-14 16:25:44
58	Заклёпка вытяжная 5х14	\N	10	\N	\N	2026-09-14 16:25:44
59	Заклёпка гаечная М8	\N	11	\N	\N	2026-09-14 16:25:44
60	Заклёпка гаечная М10 рифлёная	\N	12	\N	\N	2026-09-14 16:25:44
61	Дюбель 6х30 распорный	\N	16	\N	\N	2026-09-14 16:25:44
62	Дюбель 8х40 универсальный	\N	17	\N	\N	2026-09-14 16:25:44
63	Дюбель 10х50 для газобетона	\N	18	\N	\N	2026-09-14 16:25:44
64	Дюбель-гвоздь 6х40	\N	4	\N	\N	2026-09-14 16:25:44
65	Дюбель рамный 10х100	\N	5	\N	\N	2026-09-14 16:25:44
66	Дюбель потолочный 6х35	\N	6	\N	\N	2026-09-14 16:25:44
67	Дюбель бабочка 8х50	\N	10	\N	\N	2026-09-14 16:25:44
68	Дюбель химический для бетона	\N	11	\N	\N	2026-09-14 16:25:44
69	Дюбель фасадный 10х140	\N	12	\N	\N	2026-09-14 16:25:44
70	Дюбель пружинный 4х32	\N	16	\N	\N	2026-09-14 16:25:44
71	Хомут червячный 12-22мм	\N	17	\N	\N	2026-09-14 16:25:44
72	Хомут червячный 20-32мм	\N	18	\N	\N	2026-09-14 16:25:44
73	Хомут червячный 32-50мм	\N	4	\N	\N	2026-09-14 16:25:44
74	Хомут силовой 50-70мм	\N	5	\N	\N	2026-09-14 16:25:44
75	Хомут пластиковый 2.5х100	\N	6	\N	\N	2026-09-14 16:25:44
76	Хомут нейлоновый стяжка 3.6х200	\N	10	\N	\N	2026-09-14 16:25:44
77	Хомут металлический 25-40мм	\N	11	\N	\N	2026-09-14 16:25:44
78	Хомут сантехнический 1/2 дюйма	\N	12	\N	\N	2026-09-14 16:25:44
79	Хомут трубный 3/4 дюйма	\N	16	\N	\N	2026-09-14 16:25:44
80	Хомут дюбельный 16мм	\N	17	\N	\N	2026-09-14 16:25:44
81	Скоба такелажная 6мм	\N	18	\N	\N	2026-09-14 16:25:44
82	Скоба строительная 8мм	\N	4	\N	\N	2026-09-14 16:25:44
83	Скоба металлическая для бруса	\N	5	\N	\N	2026-09-14 16:25:44
84	Скоба мебельная уголок 30х30	\N	6	\N	\N	2026-09-14 16:25:44
85	Скоба строительная оцинкованная 10мм	\N	10	\N	\N	2026-09-14 16:25:44
86	Гвоздь строительный 2.5х50	\N	11	\N	\N	2026-09-14 16:25:44
87	Гвоздь финишный 2х30	\N	12	\N	\N	2026-09-14 16:25:44
88	Гвоздь кровельный 3.5х25	\N	16	\N	\N	2026-09-14 16:25:44
89	Гвоздь шиферный 4х100	\N	17	\N	\N	2026-09-14 16:25:44
90	Гвоздь дюбель 2.8х40	\N	18	\N	\N	2026-09-14 16:25:44
91	Анкер-клин 10х100	\N	4	\N	\N	2026-09-14 16:25:44
92	Анкер латунный цанга М8	\N	5	\N	\N	2026-09-14 16:25:44
93	Анкер рамный 12х150	\N	6	\N	\N	2026-09-14 16:25:44
94	Анкер потолочный металлический М6	\N	10	\N	\N	2026-09-14 16:25:44
95	Анкер химический капсула М12	\N	11	\N	\N	2026-09-14 16:25:44
96	Шпилька резьбовая М8х1000	\N	12	\N	\N	2026-09-14 16:25:44
97	Шпилька резьбовая М10х2000	\N	16	\N	\N	2026-09-14 16:25:44
98	Шпилька резьбовая М12х1000 оцинк	\N	17	\N	\N	2026-09-14 16:25:44
99	Шпилька резьбовая М16х2000	\N	18	\N	\N	2026-09-14 16:25:44
100	Шпилька резьбовая М20х1000 стальная	\N	4	\N	\N	2026-09-14 16:25:44
101	Лезвия для ножа трапециевидные "Vira"	\N	8	50	\N	2026-09-14 16:25:44
102	Лезвия "Vira" трапециевидные, код ОЗОН	\N	\N	32	\N	2026-09-14 16:25:44
103	Лезвия "Vira", код data-matrix	\N	\N	14	\N	2026-09-14 16:25:44
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: label_list; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.label_list (id, title, label_preset_id, created_at, updated_at) FROM stdin;
1	111	1	2026-09-14 16:25:44	2026-09-14 16:25:44
2	Хранилища 1	2	2026-09-14 16:25:44	2026-09-14 16:25:44
\.


--
-- Data for Name: label_list_m2m_item; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.label_list_m2m_item (id, label_list_id, item_id, created_at, updated_at) FROM stdin;
1	1	1	2026-09-14 16:25:44	2026-09-14 16:25:44
2	1	2	2026-09-14 16:25:44	2026-09-14 16:25:44
3	1	3	2026-09-14 16:25:44	2026-09-14 16:25:44
4	1	4	2026-09-14 16:25:44	2026-09-14 16:25:44
5	1	5	2026-09-14 16:25:44	2026-09-14 16:25:44
6	1	6	2026-09-14 16:25:44	2026-09-14 16:25:44
7	1	7	2026-09-14 16:25:44	2026-09-14 16:25:44
8	1	8	2026-09-14 16:25:44	2026-09-14 16:25:44
9	1	9	2026-09-14 16:25:44	2026-09-14 16:25:44
10	1	10	2026-09-14 16:25:44	2026-09-14 16:25:44
11	1	11	2026-09-14 16:25:44	2026-09-14 16:25:44
12	1	12	2026-09-14 16:25:44	2026-09-14 16:25:44
13	1	13	2026-09-14 16:25:44	2026-09-14 16:25:44
14	1	14	2026-09-14 16:25:44	2026-09-14 16:25:44
15	1	15	2026-09-14 16:25:44	2026-09-14 16:25:44
16	1	16	2026-09-14 16:25:44	2026-09-14 16:25:44
17	1	17	2026-09-14 16:25:44	2026-09-14 16:25:44
18	1	18	2026-09-14 16:25:44	2026-09-14 16:25:44
19	1	19	2026-09-14 16:25:44	2026-09-14 16:25:44
20	1	20	2026-09-14 16:25:44	2026-09-14 16:25:44
21	1	21	2026-09-14 16:25:44	2026-09-14 16:25:44
22	1	22	2026-09-14 16:25:44	2026-09-14 16:25:44
23	1	23	2026-09-14 16:25:44	2026-09-14 16:25:44
24	1	24	2026-09-14 16:25:44	2026-09-14 16:25:44
25	1	25	2026-09-14 16:25:44	2026-09-14 16:25:44
26	1	26	2026-09-14 16:25:44	2026-09-14 16:25:44
27	1	27	2026-09-14 16:25:44	2026-09-14 16:25:44
28	1	28	2026-09-14 16:25:44	2026-09-14 16:25:44
29	1	29	2026-09-14 16:25:44	2026-09-14 16:25:44
30	1	30	2026-09-14 16:25:44	2026-09-14 16:25:44
\.


--
-- Data for Name: label_list_m2m_store; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.label_list_m2m_store (id, label_list_id, store_id, created_at, updated_at) FROM stdin;
4	2	19	2026-09-14 18:07:15	2026-09-14 18:07:15
5	2	20	2026-09-14 18:07:18	2026-09-14 18:07:18
6	2	21	2026-09-14 18:07:21	2026-09-14 18:07:21
7	2	26	2026-09-14 18:07:23	2026-09-14 18:07:23
8	2	30	2026-09-14 18:07:27	2026-09-14 18:07:27
9	2	23	2026-09-14 18:07:30	2026-09-14 18:07:30
10	2	24	2026-09-14 18:07:33	2026-09-14 18:07:33
11	2	25	2026-09-14 18:07:35	2026-09-14 18:07:35
12	2	28	2026-09-14 18:07:37	2026-09-14 18:07:37
13	2	29	2026-09-14 18:07:40	2026-09-14 18:07:40
14	2	32	2026-09-14 19:06:40	2026-09-14 19:06:40
15	2	33	2026-09-14 19:06:43	2026-09-14 19:06:43
16	2	34	2026-09-14 19:06:45	2026-09-14 19:06:45
17	2	35	2026-09-14 19:06:47	2026-09-14 19:06:47
18	2	36	2026-09-14 19:06:49	2026-09-14 19:06:49
19	2	37	2026-09-14 19:06:52	2026-09-14 19:06:52
20	2	38	2026-09-14 19:06:54	2026-09-14 19:06:54
21	2	39	2026-09-14 19:06:57	2026-09-14 19:06:57
22	2	40	2026-09-14 19:08:48	2026-09-14 19:08:48
23	2	41	2026-09-14 19:08:50	2026-09-14 19:08:50
24	2	42	2026-09-14 19:08:52	2026-09-14 19:08:52
25	2	43	2026-09-14 19:08:54	2026-09-14 19:08:54
26	2	44	2026-09-14 19:08:56	2026-09-14 19:08:56
27	2	45	2026-09-14 19:08:58	2026-09-14 19:08:58
28	2	46	2026-09-14 19:10:07	2026-09-14 19:10:07
32	1	47	2026-09-14 19:10:58	2026-09-14 19:10:58
33	2	47	2026-09-14 19:11:49	2026-09-14 19:11:49
\.


--
-- Data for Name: label_preset; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.label_preset (id, title, page_width, page_height, page_margin_top, page_margin_right, page_margin_bottom, page_margin_left, cell_width, cell_height, cell_pad_top, cell_pad_right, cell_pad_bottom, cell_pad_left, barcode_position, barcode_text_gap, barcode_size, font_id, font_size_min, font_size_max, font_size_step, line_height_factor, created_at, updated_at) FROM stdin;
1	Лоток 1л	210	297	10	10	10	10	77	22	3	6	5	6	left	2	13	38	5	24	0.5	1.25	\N	2026-09-14 18:19:26
2	Хранилища	210	297	10	10	10	10	95	20	2	2	2	2	left	2	13	38	5	24	0.5	1.25	\N	2026-09-14 18:19:26
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_08_06_134533_create_image_table	1
5	2026_08_06_134538_create_file_table	1
6	2026_08_06_134552_create_store_table	1
7	2026_08_06_134553_create_item_table	1
8	2026_08_06_134555_create_code_table	1
9	2026_08_06_134629_create_image_m2m_store_table	1
10	2026_08_06_134635_create_image_m2m_item_table	1
11	2026_08_07_173000_create_label_preset_table	1
12	2026_08_07_183000_create_label_list_table	1
13	2026_08_07_190000_add_store_search_vector	1
14	2026_08_07_191000_add_item_search_vector	1
15	2026_08_07_192000_add_pg_trgm	1
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
UJVAG4ukU2szM2LTtrciVUeDtZL5ut4NVfn1J2RG	\N	10.42.0.189	curl/8.14.1	eyJfdG9rZW4iOiJkckd6cjRCbzZxTkJEVkJUSDlIZGFBZUNaY0dVME5MUDhnZ0Q0UkY1IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2FwaS5kZXYxMS5ydSIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==	1789406652
YouaEP9kVR0kKfyCqIrMqBAfHt9WBX5um7H9YFJI	\N	10.42.0.189	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36	eyJfdG9rZW4iOiJVdXhtT0h5em5PbUhrVHQyZWJBMWxJT1ZKN2hVTjJCWWNUUkRWNzN3IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2FwaS5kZXYxMS5ydSIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==	1789407032
\.


--
-- Data for Name: store; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.store (id, title, title_print, parent_id, created_at, updated_at) FROM stdin;
1	Шкаф 1	\N	\N	\N	2026-09-14 16:25:44
2	Полка 1-1	\N	1	\N	2026-09-14 16:25:44
3	Полка 1-2	\N	1	\N	2026-09-14 16:25:44
4	Коробка 1	\N	2	\N	2026-09-14 16:25:44
5	Коробка 2	\N	3	\N	2026-09-14 16:25:44
6	Коробка 3	\N	3	\N	2026-09-14 16:25:44
7	Шкаф 2	\N	\N	\N	2026-09-14 16:25:44
8	Полка 2-1	\N	7	\N	2026-09-14 16:25:44
9	Полка 2-2	\N	7	\N	2026-09-14 16:25:44
10	Коробка 4	\N	8	\N	2026-09-14 16:25:44
11	Коробка 5	\N	9	\N	2026-09-14 16:25:44
12	Коробка 6	\N	9	\N	2026-09-14 16:25:44
13	Шкаф 3	\N	\N	\N	2026-09-14 16:25:44
14	Полка 3-1	\N	13	\N	2026-09-14 16:25:44
15	Полка 3-2	\N	13	\N	2026-09-14 16:25:44
16	Коробка 7	\N	14	\N	2026-09-14 16:25:44
17	Коробка 8	\N	15	\N	2026-09-14 16:25:44
18	Коробка 9	\N	15	\N	2026-09-14 16:25:44
19	Шкаф в котельной	\N	\N	\N	2026-09-14 16:25:44
26	Коробка 1	\N	21	\N	2026-09-14 16:25:44
20	Полка A	\N	19	\N	2026-09-14 17:26:37
21	Полка B	\N	19	\N	2026-09-14 17:26:46
23	Полка C	\N	19	\N	2026-09-14 17:26:55
24	Полка D	\N	19	\N	2026-09-14 17:50:07
25	Полка E	\N	19	\N	2026-09-14 17:50:20
28	Полка F	\N	19	2026-09-14 17:54:08	2026-09-14 17:54:08
29	Полка G	\N	19	2026-09-14 17:54:14	2026-09-14 17:54:14
30	Контейнер 1	\N	21	2026-09-14 17:55:06	2026-09-14 17:55:06
32	Контейнер 3	\N	21	2026-09-14 19:05:58	2026-09-14 19:05:58
33	Контейнер 4	\N	21	2026-09-14 19:06:05	2026-09-14 19:06:05
34	Контейнер 5	\N	21	2026-09-14 19:06:10	2026-09-14 19:06:10
35	Контейнер 6	\N	21	2026-09-14 19:06:13	2026-09-14 19:06:13
36	Контейнер 7	\N	21	2026-09-14 19:06:16	2026-09-14 19:06:16
37	Контейнер 8	\N	21	2026-09-14 19:06:19	2026-09-14 19:06:19
38	Контейнер 9	\N	21	2026-09-14 19:06:23	2026-09-14 19:06:23
39	Контейнер 10	\N	21	2026-09-14 19:06:30	2026-09-14 19:06:30
40	Контейнер 11	\N	21	2026-09-14 19:07:49	2026-09-14 19:07:49
41	Контейнер 12	\N	21	2026-09-14 19:07:52	2026-09-14 19:07:52
42	Контейнер 13	\N	21	2026-09-14 19:07:54	2026-09-14 19:07:54
43	Контейнер 14	\N	21	2026-09-14 19:07:57	2026-09-14 19:07:57
44	Контейнер 15	\N	21	2026-09-14 19:08:20	2026-09-14 19:08:34
45	Контейнер 16	\N	21	2026-09-14 19:08:39	2026-09-14 19:08:39
46	Контейнер 17	\N	21	2026-09-14 19:09:48	2026-09-14 19:09:48
47	Контейнер 18	\N	21	2026-09-14 19:09:52	2026-09-14 19:09:52
48	Контейнер 19	\N	21	2026-09-14 19:09:55	2026-09-14 19:09:55
49	Контейнер 20	\N	21	2026-09-14 19:10:00	2026-09-14 19:10:00
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at) FROM stdin;
1	Test User	test@example.com	\N	$2y$12$lJBN.SoqLtby2.3z2Zbnuu2ZyYOMFSPXrExrOjbSFgiXTtsj5WDta	\N	2026-09-14 16:25:44	2026-09-14 16:25:44
\.


--
-- Name: code_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.code_id_seq', 152, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, true);


--
-- Name: file_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.file_id_seq', 1, true);


--
-- Name: font_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.font_id_seq', 76, true);


--
-- Name: image_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.image_id_seq', 1, true);


--
-- Name: image_m2m_item_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.image_m2m_item_id_seq', 1, true);


--
-- Name: image_m2m_label_preset_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.image_m2m_label_preset_id_seq', 1, true);


--
-- Name: image_m2m_store_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.image_m2m_store_id_seq', 1, true);


--
-- Name: item_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.item_id_seq', 103, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, true);


--
-- Name: label_list_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.label_list_id_seq', 2, true);


--
-- Name: label_list_m2m_item_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.label_list_m2m_item_id_seq', 41, true);


--
-- Name: label_list_m2m_store_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.label_list_m2m_store_id_seq', 33, true);


--
-- Name: label_preset_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.label_preset_id_seq', 2, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 15, true);


--
-- Name: store_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.store_id_seq', 49, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.users_id_seq', 1, true);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: code code_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.code
    ADD CONSTRAINT code_code_unique UNIQUE (code);


--
-- Name: code code_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.code
    ADD CONSTRAINT code_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: file file_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.file
    ADD CONSTRAINT file_pkey PRIMARY KEY (id);


--
-- Name: font font_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.font
    ADD CONSTRAINT font_key_unique UNIQUE (key);


--
-- Name: font font_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.font
    ADD CONSTRAINT font_name_unique UNIQUE (name);


--
-- Name: font font_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.font
    ADD CONSTRAINT font_pkey PRIMARY KEY (id);


--
-- Name: image_m2m_item image_m2m_item_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_item
    ADD CONSTRAINT image_m2m_item_pkey PRIMARY KEY (id);


--
-- Name: image_m2m_label_preset image_m2m_label_preset_image_id_label_preset_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_label_preset
    ADD CONSTRAINT image_m2m_label_preset_image_id_label_preset_id_unique UNIQUE (image_id, label_preset_id);


--
-- Name: image_m2m_label_preset image_m2m_label_preset_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_label_preset
    ADD CONSTRAINT image_m2m_label_preset_pkey PRIMARY KEY (id);


--
-- Name: image_m2m_store image_m2m_store_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_store
    ADD CONSTRAINT image_m2m_store_pkey PRIMARY KEY (id);


--
-- Name: image image_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image
    ADD CONSTRAINT image_pkey PRIMARY KEY (id);


--
-- Name: item item_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item
    ADD CONSTRAINT item_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: label_list_m2m_item label_list_m2m_item_label_list_id_item_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_item
    ADD CONSTRAINT label_list_m2m_item_label_list_id_item_id_unique UNIQUE (label_list_id, item_id);


--
-- Name: label_list_m2m_item label_list_m2m_item_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_item
    ADD CONSTRAINT label_list_m2m_item_pkey PRIMARY KEY (id);


--
-- Name: label_list_m2m_store label_list_m2m_store_label_list_id_store_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_store
    ADD CONSTRAINT label_list_m2m_store_label_list_id_store_id_unique UNIQUE (label_list_id, store_id);


--
-- Name: label_list_m2m_store label_list_m2m_store_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_store
    ADD CONSTRAINT label_list_m2m_store_pkey PRIMARY KEY (id);


--
-- Name: label_list label_list_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list
    ADD CONSTRAINT label_list_pkey PRIMARY KEY (id);


--
-- Name: label_list label_list_title_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list
    ADD CONSTRAINT label_list_title_unique UNIQUE (title);


--
-- Name: label_preset label_preset_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_preset
    ADD CONSTRAINT label_preset_pkey PRIMARY KEY (id);


--
-- Name: label_preset label_preset_title_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_preset
    ADD CONSTRAINT label_preset_title_unique UNIQUE (title);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: store store_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.store
    ADD CONSTRAINT store_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: code_item_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX code_item_id_index ON public.code USING btree (item_id);


--
-- Name: code_store_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX code_store_id_index ON public.code USING btree (store_id);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: idx_item_search; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_search ON public.item USING gin (search_vector);


--
-- Name: idx_item_title_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_item_title_trgm ON public.item USING gin (title public.gin_trgm_ops);


--
-- Name: idx_store_search; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_store_search ON public.store USING gin (search_vector);


--
-- Name: idx_store_title_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_store_title_trgm ON public.store USING gin (title public.gin_trgm_ops);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: store_parent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX store_parent_id_index ON public.store USING btree (parent_id);


--
-- Name: code code_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.code
    ADD CONSTRAINT code_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.item(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: code code_store_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.code
    ADD CONSTRAINT code_store_id_foreign FOREIGN KEY (store_id) REFERENCES public.store(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: image_m2m_label_preset image_m2m_label_preset_image_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_label_preset
    ADD CONSTRAINT image_m2m_label_preset_image_id_foreign FOREIGN KEY (image_id) REFERENCES public.image(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: image_m2m_label_preset image_m2m_label_preset_label_preset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.image_m2m_label_preset
    ADD CONSTRAINT image_m2m_label_preset_label_preset_id_foreign FOREIGN KEY (label_preset_id) REFERENCES public.label_preset(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: item item_store_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.item
    ADD CONSTRAINT item_store_id_foreign FOREIGN KEY (store_id) REFERENCES public.store(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: label_list label_list_label_preset_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list
    ADD CONSTRAINT label_list_label_preset_id_foreign FOREIGN KEY (label_preset_id) REFERENCES public.label_preset(id) ON DELETE CASCADE;


--
-- Name: label_list_m2m_item label_list_m2m_item_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_item
    ADD CONSTRAINT label_list_m2m_item_item_id_foreign FOREIGN KEY (item_id) REFERENCES public.item(id) ON DELETE CASCADE;


--
-- Name: label_list_m2m_item label_list_m2m_item_label_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_item
    ADD CONSTRAINT label_list_m2m_item_label_list_id_foreign FOREIGN KEY (label_list_id) REFERENCES public.label_list(id) ON DELETE CASCADE;


--
-- Name: label_list_m2m_store label_list_m2m_store_label_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_store
    ADD CONSTRAINT label_list_m2m_store_label_list_id_foreign FOREIGN KEY (label_list_id) REFERENCES public.label_list(id) ON DELETE CASCADE;


--
-- Name: label_list_m2m_store label_list_m2m_store_store_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_list_m2m_store
    ADD CONSTRAINT label_list_m2m_store_store_id_foreign FOREIGN KEY (store_id) REFERENCES public.store(id) ON DELETE CASCADE;


--
-- Name: label_preset label_preset_font_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_preset
    ADD CONSTRAINT label_preset_font_id_foreign FOREIGN KEY (font_id) REFERENCES public.font(id) ON DELETE SET NULL;


--
-- Name: store store_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.store
    ADD CONSTRAINT store_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.store(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict KAteYA8IXp0mr3ZB84ss66fui3RXwT8f9SlhwbHYbbxyblC7FvgpO20LkE52IVG


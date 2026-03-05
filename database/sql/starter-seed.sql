-- =============================================================================
-- Overlabels starter seed
-- Generated: 2026-03-05 22:13:01
--
-- Tables: users, template_tag_categories, template_tags, kits, kit_templates, overlay_templates, overlay_controls, event_template_mappings
-- Sensitive user fields (tokens, twitch_data, webhook_secret) are stripped.
-- Run AFTER php artisan migrate on a fresh database.
--
-- Import: psql -U postgres -d <dbname> -f database/sql/starter-seed.sql
-- =============================================================================

BEGIN;

SET session_replication_role = 'replica';

-- Clear existing starter data
TRUNCATE public.event_template_mappings CASCADE;
TRUNCATE public.overlay_controls CASCADE;
TRUNCATE public.overlay_templates CASCADE;
TRUNCATE public.kit_templates CASCADE;
TRUNCATE public.kits CASCADE;
TRUNCATE public.template_tags CASCADE;
TRUNCATE public.template_tag_categories CASCADE;
TRUNCATE public.users CASCADE;

-- users
INSERT INTO public.users (id, name, email, twitch_id, email_verified_at, password, remember_token, created_at, updated_at, avatar, access_token, refresh_token, token_expires_at, twitch_data, eventsub_connected_at, eventsub_auto_connect, onboarded_at, webhook_secret, role, is_system_user, deleted_at, icon) VALUES (21, 'Ghost User', 'ghost@overlabels.internal', 'x', '2026-03-05 20:39:52', '$2y$04$1juCoMeWdcqMef.cpkioIuWpFMG.E6CuN6Kdo0vKZLbe924cLsh.q', NULL, '2026-03-05 20:39:52', '2026-03-05 20:39:52', NULL, NULL, NULL, NULL, NULL, NULL, true, NULL, NULL, 'user', true, NULL, NULL);
INSERT INTO public.users (id, name, email, twitch_id, email_verified_at, password, remember_token, created_at, updated_at, avatar, access_token, refresh_token, token_expires_at, twitch_data, eventsub_connected_at, eventsub_auto_connect, onboarded_at, webhook_secret, role, is_system_user, deleted_at, icon) VALUES (50, 'JasperDiscovers', 'jaspervdmeer@gmail.com', '73327367', '2026-03-05 20:44:43', '$2y$12$qmrPKvgKm/VNhGyoBjgquOGtq/RS06f3F9wviCXhF9zv8OSW2k6GO', NULL, '2026-03-05 20:44:43', '2026-03-05 22:09:11', 'https://static-cdn.jtvnw.net/jtv_user_pictures/1d0df896-13c2-4f15-b6ca-f7d40d2fffc5-profile_image-300x300.png', NULL, NULL, NULL, NULL, NULL, true, NULL, NULL, 'admin', false, NULL, 'cannabis');

-- template_tag_categories
INSERT INTO public.template_tag_categories (id, name, display_name, description, is_group, sort_order, created_at, updated_at, user_id) VALUES (1, 'user', 'User Information', 'Basic user account information', false, 0, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 50);
INSERT INTO public.template_tag_categories (id, name, display_name, description, is_group, sort_order, created_at, updated_at, user_id) VALUES (2, 'channel', 'Channel Information', 'Channel settings and current stream info', false, 1, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 50);
INSERT INTO public.template_tag_categories (id, name, display_name, description, is_group, sort_order, created_at, updated_at, user_id) VALUES (3, 'followers', 'Followers', 'Follower statistics and latest follower info', false, 2, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 50);
INSERT INTO public.template_tag_categories (id, name, display_name, description, is_group, sort_order, created_at, updated_at, user_id) VALUES (4, 'followed', 'Followed Channels', 'Channels that this user follows', false, 3, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 50);
INSERT INTO public.template_tag_categories (id, name, display_name, description, is_group, sort_order, created_at, updated_at, user_id) VALUES (5, 'subscribers', 'Subscribers', 'Subscriber statistics and latest subscriber info', false, 4, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 50);
INSERT INTO public.template_tag_categories (id, name, display_name, description, is_group, sort_order, created_at, updated_at, user_id) VALUES (9, 'other', 'Other', 'Template tags related to other', false, 8, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 50);

-- template_tags
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (1, 9, 'user', '[[[user]]]', 'user', 'array', 'User', 'Template tag for User. List of values.', '{"id":"73327367","login":"jasperdiscovers","display_name":"JasperDiscovers"}', '{"array_join":", ","max_items":3}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (2, 9, 'user_count', '[[[user_count]]]', 'user_count', 'integer', 'Count', 'Template tag for Count. Numeric value.', '11', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (3, 1, 'user_id', '[[[user_id]]]', 'user.id', 'string', 'Id', 'User ID', '"73327367"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (4, 1, 'user_login', '[[[user_login]]]', 'user.login', 'string', 'Login', 'User login name', '"jasperdiscovers"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (5, 1, 'user_name', '[[[user_name]]]', 'user.display_name', 'string', 'Name', 'User display name', '"JasperDiscovers"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (6, 1, 'user_type', '[[[user_type]]]', 'user.type', 'string', 'Type', 'User type', '""', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (7, 1, 'user_broadcaster_type', '[[[user_broadcaster_type]]]', 'user.broadcaster_type', 'string', 'Broadcaster Type', 'Broadcaster type (partner, affiliate, etc.)', '"affiliate"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (8, 1, 'user_description', '[[[user_description]]]', 'user.description', 'string', 'Description', 'User bio/description', '"On hiatus. Thank you all."', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (9, 1, 'user_avatar', '[[[user_avatar]]]', 'user.profile_image_url', 'url', 'Avatar', 'Profile image URL', '"https:\/\/static-cdn.jtvnw.net\/jtv_user_pictures\/1d0df896-13c2-4f15-b6ca-f7d40d2fffc5-profile_image-300x300.png"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (10, 1, 'user_offline_banner', '[[[user_offline_banner]]]', 'user.offline_image_url', 'url', 'Offline Banner', 'Offline image URL', '"https:\/\/static-cdn.jtvnw.net\/jtv_user_pictures\/8bf8cae6-f42a-4fd7-815a-b7f27a2bf848-channel_offline_image-1920x1080.jpeg"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (11, 1, 'user_view_count', '[[[user_view_count]]]', 'user.view_count', 'integer', 'View Count', 'Total view count', '0', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (12, 1, 'user_email', '[[[user_email]]]', 'user.email', 'string', 'Email', 'User email', '"jaspervdmeer@gmail.com"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (13, 1, 'user_created', '[[[user_created]]]', 'user.created_at', 'datetime', 'Created', 'Account creation date', '"2014-10-18T23:16:54Z"', '{"date_format":"d-m-Y H:i","available_formats":{"d-m-Y H:i":"DD-MM-YYYY HH:MM","Y-m-d H:i:s":"YYYY-MM-DD HH:MM:SS","M j, Y":"Month Day, Year","D, M j Y":"Day, Month Day Year"}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (14, 9, 'channel', '[[[channel]]]', 'channel', 'array', 'Channel', 'Template tag for Channel. List of values.', '{"broadcaster_id":"73327367","broadcaster_login":"jasperdiscovers","broadcaster_name":"JasperDiscovers"}', '{"array_join":", ","max_items":3}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (15, 9, 'channel_count', '[[[channel_count]]]', 'channel_count', 'integer', 'Count', 'Template tag for Count. Numeric value.', '11', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (16, 2, 'channel_id', '[[[channel_id]]]', 'channel.broadcaster_id', 'string', 'Id', 'Channel ID', '"73327367"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (17, 2, 'channel_login', '[[[channel_login]]]', 'channel.broadcaster_login', 'string', 'Login', 'Channel login name', '"jasperdiscovers"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (18, 2, 'channel_name', '[[[channel_name]]]', 'channel.broadcaster_name', 'string', 'Name', 'Channel display name', '"JasperDiscovers"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (19, 2, 'channel_language', '[[[channel_language]]]', 'channel.broadcaster_language', 'string', 'Language', 'Channel language', '"en"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (20, 2, 'channel_game_id', '[[[channel_game_id]]]', 'channel.game_id', 'string', 'Game Id', 'Current game/category ID', '"247865501"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (21, 2, 'channel_game', '[[[channel_game]]]', 'channel.game_name', 'string', 'Game', 'Current game/category name', '"Just Catting"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (22, 2, 'channel_title', '[[[channel_title]]]', 'channel.title', 'string', 'Title', 'Stream title', '"A hiatus is not permanent"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (23, 2, 'channel_delay', '[[[channel_delay]]]', 'channel.delay', 'integer', 'Delay', 'Stream delay in seconds', '0', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (24, 2, 'channel_tags_0', '[[[channel_tags_0]]]', 'channel.tags.0', 'string', 'Tags 0', 'Template tag for Tags 0. Text value.', '"English"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (25, 2, 'channel_tags_1', '[[[channel_tags_1]]]', 'channel.tags.1', 'string', 'Tags 1', 'Template tag for Tags 1. Text value.', '"Dutch"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (26, 2, 'channel_tags_2', '[[[channel_tags_2]]]', 'channel.tags.2', 'string', 'Tags 2', 'Template tag for Tags 2. Text value.', '"Hello"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (27, 2, 'channel_tags_3', '[[[channel_tags_3]]]', 'channel.tags.3', 'string', 'Tags 3', 'Template tag for Tags 3. Text value.', '"Love"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (28, 2, 'channel_is_branded', '[[[channel_is_branded]]]', 'channel.is_branded_content', 'boolean', 'Is Branded', 'Whether channel has branded content', 'false', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (29, 4, 'followed_channels', '[[[followed_channels]]]', 'followed_channels', 'array', 'Channels', 'Template tag for Channels. List of values.', '{"total":174,"data":[{"broadcaster_id":"512014493","broadcaster_login":"adeypdj","broadcaster_name":"adeypdj","followed_at":"2026-03-02T16:09:47Z"},{"broadcaster_id":"577527596","broadcaster_login":"lilypita","broadcaster_name":"LilyPita","followed_at":"2026-02-23T20:47:50Z"},{"broadcaster_id":"1202785947","broadcaster_login":"emceereality","broadcaster_name":"EmCeeReality","followed_at":"2026-02-10T01:52:22Z"},{"broadcaster_id":"43611294","broadcaster_login":"fryettdnb","broadcaster_name":"FryettDnB","followed_at":"2026-02-07T23:10:30Z"},{"broadcaster_id":"155205501","broadcaster_login":"mush_live","broadcaster_name":"MUSH_live","followed_at":"2026-02-05T22:40:56Z"},{"broadcaster_id":"140043801","broadcaster_login":"epicvoyages","broadcaster_name":"EpicVoyages","followed_at":"2026-02-04T13:26:03Z"},{"broadcaster_id":"58543450","broadcaster_login":"helmahof","broadcaster_name":"Helmahof","followed_at":"2026-01-30T21:25:26Z"},{"broadcaster_id":"506652873","broadcaster_login":"andrewfetch","broadcaster_name":"AndrewFetch","followed_at":"2026-01-26T23:01:48Z"},{"broadcaster_id":"168318872","broadcaster_login":"man_of_house","broadcaster_name":"Man_of_House","followed_at":"2026-01-22T01:44:16Z"},{"broadcaster_id":"576318433","broadcaster_login":"bubblegumnihilists","broadcaster_name":"BubblegumNihilists","followed_at":"2026-01-20T23:07:59Z"},{"broadcaster_id":"79268687","broadcaster_login":"karvaooppeli","broadcaster_name":"Karvaooppeli","followed_at":"2026-01-17T15:13:48Z"},{"broadcaster_id":"220476955","broadcaster_login":"ishowspeed","broadcaster_name":"IShowSpeed","followed_at":"2026-01-13T14:16:47Z"},{"broadcaster_id":"1375741696","broadcaster_login":"albertingmar","broadcaster_name":"AlbertIngmar","followed_at":"2026-01-09T21:14:13Z"},{"broadcaster_id":"402923998","broadcaster_login":"grimreaperwithalawnmower","broadcaster_name":"GrimReaperWithALawnmower","followed_at":"2025-12-23T23:36:35Z"},{"broadcaster_id":"776558501","broadcaster_login":"fredagainagain","broadcaster_name":"fredagainagain","followed_at":"2025-11-21T21:22:42Z"},{"broadcaster_id":"884641444","broadcaster_login":"alldaynyc","broadcaster_name":"ALLDAYNYC","followed_at":"2025-11-18T22:44:19Z"},{"broadcaster_id":"1310107212","broadcaster_login":"artiguff","broadcaster_name":"Artiguff","followed_at":"2025-11-16T00:31:55Z"},{"broadcaster_id":"48571756","broadcaster_login":"grian","broadcaster_name":"Grian","followed_at":"2025-11-11T23:59:50Z"},{"broadcaster_id":"38871579","broadcaster_login":"feinberg","broadcaster_name":"Feinberg","followed_at":"2025-11-11T23:34:03Z"},{"broadcaster_id":"1139410309","broadcaster_login":"harveyridesbikes","broadcaster_name":"HarveyRidesBikes","followed_at":"2025-11-01T14:54:40Z"}],"pagination":{"cursor":"eyJiIjpudWxsLCJhIjp7IkN1cnNvciI6ImV5SjBjQ0k2SW5WelpYSTZOek16TWpjek5qYzZabTlzYkc5M2N5SXNJblJ6SWpvaWRYTmxjam94TVRNNU5ERXdNekE1SWl3aWFYQWlPaUoxYzJWeU9qY3pNekkzTXpZM09tWnZiR3h2ZDNNaUxDSnBjeUk2SWpFM05qSXdNRGc0T0RBME9UazJOelUzTmpZaWZRPT0ifX0"}}', '{"array_join":", ","max_items":3}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (30, 4, 'followed_channels_count', '[[[followed_channels_count]]]', 'followed_channels_count', 'integer', 'Channels Count', 'Template tag for Channels Count. Numeric value.', '3', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (31, 4, 'followed_total', '[[[followed_total]]]', 'followed_channels.total', 'integer', 'Total', 'Total followed channels', '174', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (34, 4, 'followed_latest_id', '[[[followed_latest_id]]]', 'followed_channels.data.0.broadcaster_id', 'string', 'Latest Id', 'Latest followed channel ID', '"512014493"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (35, 4, 'followed_latest_login', '[[[followed_latest_login]]]', 'followed_channels.data.0.broadcaster_login', 'string', 'Latest Login', 'Latest followed channel login', '"adeypdj"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (36, 4, 'followed_latest_name', '[[[followed_latest_name]]]', 'followed_channels.data.0.broadcaster_name', 'string', 'Latest Name', 'Latest followed channel name', '"adeypdj"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (37, 4, 'followed_latest_date', '[[[followed_latest_date]]]', 'followed_channels.data.0.followed_at', 'datetime', 'Latest Date', 'Latest follow date', '"2026-03-02T16:09:47Z"', '{"date_format":"d-m-Y H:i","available_formats":{"d-m-Y H:i":"DD-MM-YYYY HH:MM","Y-m-d H:i:s":"YYYY-MM-DD HH:MM:SS","M j, Y":"Month Day, Year","D, M j Y":"Day, Month Day Year"}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (152, 4, 'followed_channels_pagination_cursor', '[[[followed_channels_pagination_cursor]]]', 'followed_channels.pagination.cursor', 'string', 'Channels Pagination Cursor', 'Template tag for Channels Pagination Cursor. Text value.', '"eyJiIjpudWxsLCJhIjp7IkN1cnNvciI6ImV5SjBjQ0k2SW5WelpYSTZOek16TWpjek5qYzZabTlzYkc5M2N5SXNJblJ6SWpvaWRYTmxjam94TVRNNU5ERXdNekE1SWl3aWFYQWlPaUoxYzJWeU9qY3pNekkzTXpZM09tWnZiR3h2ZDNNaUxDSnBjeUk2SWpFM05qSXdNRGc0T0RBME9UazJOelUzTmpZaWZRPT0ifX0"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (153, 2, 'channel_followers', '[[[channel_followers]]]', 'channel_followers', 'array', 'Followers', 'Template tag for Followers. List of values.', '{"total":1522,"data":[{"user_id":"645483548","user_login":"markumdnb","user_name":"markumdnb","followed_at":"2026-02-10T01:53:08Z"},{"user_id":"1386643141","user_login":"paula_s_1977","user_name":"Paula_S_1977","followed_at":"2026-01-21T13:53:43Z"},{"user_id":"486863015","user_login":"squaredave_","user_name":"squaredave_","followed_at":"2026-01-20T23:19:35Z"},{"user_id":"580796097","user_login":"carmenassertive","user_name":"carmenassertive","followed_at":"2026-01-10T21:55:46Z"},{"user_id":"570483490","user_login":"jusjacklive","user_name":"jusjacklive","followed_at":"2026-01-05T20:25:52Z"},{"user_id":"89240033","user_login":"jaypocalypsemusic","user_name":"JaypocalypseMusic","followed_at":"2026-01-02T17:59:05Z"},{"user_id":"528285078","user_login":"lills300","user_name":"lills300","followed_at":"2025-12-26T22:04:42Z"},{"user_id":"703208402","user_login":"mjd820","user_name":"mjd820","followed_at":"2025-12-11T12:29:39Z"},{"user_id":"50496845","user_login":"realmwalker1701","user_name":"RealmWalker1701","followed_at":"2025-11-19T14:38:46Z"},{"user_id":"72012304","user_login":"randombrein","user_name":"Randombrein","followed_at":"2025-09-29T16:26:11Z"},{"user_id":"48794513","user_login":"pachiefico","user_name":"Pachiefico","followed_at":"2025-09-24T17:11:32Z"},{"user_id":"1130071166","user_login":"overlabels","user_name":"overlabels","followed_at":"2025-08-19T15:59:36Z"},{"user_id":"474386118","user_login":"kuromi__sanrio","user_name":"Kuromi__Sanrio","followed_at":"2025-06-08T18:39:52Z"},{"user_id":"39670679","user_login":"electrinchen","user_name":"electrinchen","followed_at":"2025-06-03T13:11:26Z"},{"user_id":"857972404","user_login":"snottsalt","user_name":"Snottsalt","followed_at":"2025-05-31T04:43:40Z"},{"user_id":"134977832","user_login":"gantmor","user_name":"Gantmor","followed_at":"2025-05-30T16:21:44Z"},{"user_id":"38036204","user_login":"messiah4hire","user_name":"messiah4hire","followed_at":"2025-05-20T23:22:48Z"},{"user_id":"1143352486","user_login":"hallohamburg","user_name":"hallohamburg","followed_at":"2025-05-10T12:37:42Z"},{"user_id":"50578227","user_login":"thebugsarebad","user_name":"thebugsarebad","followed_at":"2025-04-19T19:46:17Z"},{"user_id":"413804340","user_login":"chrislack140","user_name":"chrislack140","followed_at":"2025-04-11T20:34:52Z"}],"pagination":{"cursor":"eyJiIjpudWxsLCJhIjp7IkN1cnNvciI6ImV5SjBjQ0k2SW5WelpYSTZOREV6T0RBME16UXdPbVp2Ykd4dmQzTWlMQ0owY3lJNkluVnpaWEk2TnpNek1qY3pOamNpTENKcGNDSTZJblZ6WlhJNk56TXpNamN6TmpjNlptOXNiRzkzWldSZllua2lMQ0pwY3lJNklqRTNORFEwTURNMk9USTBOakk1Tnprd016VWlmUT09In19"}}', '{"array_join":", ","max_items":3}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (154, 2, 'channel_followers_count', '[[[channel_followers_count]]]', 'channel_followers_count', 'integer', 'Followers Count', 'Template tag for Followers Count. Numeric value.', '3', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (155, 3, 'followers_total', '[[[followers_total]]]', 'channel_followers.total', 'integer', 'Total', 'Total number of followers', '1522', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (158, 3, 'followers_latest_user_id', '[[[followers_latest_user_id]]]', 'channel_followers.data.0.user_id', 'string', 'Latest User Id', 'Template tag for Latest User Id. Text value.', '"645483548"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (159, 3, 'followers_latest_user_login', '[[[followers_latest_user_login]]]', 'channel_followers.data.0.user_login', 'string', 'Latest User Login', 'Template tag for Latest User Login. Text value.', '"markumdnb"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (160, 3, 'followers_latest_user_name', '[[[followers_latest_user_name]]]', 'channel_followers.data.0.user_name', 'string', 'Latest User Name', 'Template tag for Latest User Name. Text value.', '"markumdnb"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (161, 3, 'followers_latest_date', '[[[followers_latest_date]]]', 'channel_followers.data.0.followed_at', 'datetime', 'Latest Date', 'Latest follow date', '"2026-02-10T01:53:08Z"', '{"date_format":"d-m-Y H:i","available_formats":{"d-m-Y H:i":"DD-MM-YYYY HH:MM","Y-m-d H:i:s":"YYYY-MM-DD HH:MM:SS","M j, Y":"Month Day, Year","D, M j Y":"Day, Month Day Year"}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (276, 2, 'channel_followers_pagination_cursor', '[[[channel_followers_pagination_cursor]]]', 'channel_followers.pagination.cursor', 'string', 'Followers Pagination Cursor', 'Template tag for Followers Pagination Cursor. Text value.', '"eyJiIjpudWxsLCJhIjp7IkN1cnNvciI6ImV5SjBjQ0k2SW5WelpYSTZOREV6T0RBME16UXdPbVp2Ykd4dmQzTWlMQ0owY3lJNkluVnpaWEk2TnpNek1qY3pOamNpTENKcGNDSTZJblZ6WlhJNk56TXpNamN6TmpjNlptOXNiRzkzWldSZllua2lMQ0pwY3lJNklqRTNORFEwTURNMk9USTBOakk1Tnprd016VWlmUT09In19"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (277, 9, 'subscribers', '[[[subscribers]]]', 'subscribers', 'array', 'Subscribers', 'Template tag for Subscribers. List of values.', '{"data":[{"broadcaster_id":"73327367","broadcaster_login":"jasperdiscovers","broadcaster_name":"JasperDiscovers","gifter_id":"","gifter_login":"","gifter_name":"","is_gift":false,"plan_name":"Tier 1 Sub","tier":"1000","user_id":"97930127","user_name":"f1los2","user_login":"f1los2"},{"broadcaster_id":"73327367","broadcaster_login":"jasperdiscovers","broadcaster_name":"JasperDiscovers","gifter_id":"","gifter_login":"","gifter_name":"","is_gift":false,"plan_name":"Tier 1 Sub","tier":"1000","user_id":"73596002","user_name":"Yurneromasaki_Kozuka","user_login":"yurneromasaki_kozuka"},{"broadcaster_id":"73327367","broadcaster_login":"jasperdiscovers","broadcaster_name":"JasperDiscovers","gifter_id":"","gifter_login":"","gifter_name":"","is_gift":false,"plan_name":"Tier 3 Sub","tier":"3000","user_id":"73327367","user_name":"JasperDiscovers","user_login":"jasperdiscovers"}],"pagination":{"cursor":"eyJiIjp7IkN1cnNvciI6ImV5SkpkR1Z0U1VRdFUzUmhjblJ6UVhRdFNVUWlPbnNpUWlJNmJuVnNiQ3dpUWs5UFRDSTZiblZzYkN3aVFsTWlPbTUxYkd3c0lrd2lPbTUxYkd3c0lrMGlPbTUxYkd3c0lrNGlPbTUxYkd3c0lrNVRJanB1ZFd4c0xDSk9WVXhNSWpwdWRXeHNMQ0pUSWpvaU1EQXdNREF3TXpBek1qVXlNVFExTFRJd01qRXRNRGt0TURsVU1UZzZNemM2TXpkYUxURjRkV3d6Y1hWVU1HZHFUR3BIZVhkS1IzaEdiVVJwWTJWWGFTSXNJbE5USWpwdWRXeHNmU3dpU1hSbGJVOTNibVZ5U1VRaU9uc2lRaUk2Ym5Wc2JDd2lRazlQVENJNmJuVnNiQ3dpUWxNaU9tNTFiR3dzSWt3aU9tNTFiR3dzSWswaU9tNTFiR3dzSWs0aU9tNTFiR3dzSWs1VElqcHVkV3hzTENKT1ZVeE1JanB1ZFd4c0xDSlRJam9pTnpNek1qY3pOamNpTENKVFV5STZiblZzYkgwc0lrbDBaVzFQZDI1bGNrbEVMVWwwWlcxSlJDMVRkR0Z5ZEhOQmRDMUpSQ0k2ZXlKQ0lqcHVkV3hzTENKQ1QwOU1JanB1ZFd4c0xDSkNVeUk2Ym5Wc2JDd2lUQ0k2Ym5Wc2JDd2lUU0k2Ym5Wc2JDd2lUaUk2Ym5Wc2JDd2lUbE1pT201MWJHd3NJazVWVEV3aU9tNTFiR3dzSWxNaU9pSXdNREF3TURBd056TXpNamN6TmpjdE1EQXdNREF3TXpBek1qVXlNVFExTFRJd01qRXRNRGt0TURsVU1UZzZNemM2TXpkYUxURjRkV3d6Y1hWVU1HZHFUR3BIZVhkS1IzaEdiVVJwWTJWWGFTSXNJbE5USWpwdWRXeHNmU3dpVDNkdVpYSkpSQ0k2ZXlKQ0lqcHVkV3hzTENKQ1QwOU1JanB1ZFd4c0xDSkNVeUk2Ym5Wc2JDd2lUQ0k2Ym5Wc2JDd2lUU0k2Ym5Wc2JDd2lUaUk2Ym5Wc2JDd2lUbE1pT201MWJHd3NJazVWVEV3aU9tNTFiR3dzSWxNaU9pSTVOemt6TURFeU55SXNJbE5USWpwdWRXeHNmWDA9In0sImEiOnsiQ3Vyc29yIjoiZXlKSmRHVnRTVVF0VTNSaGNuUnpRWFF0U1VRaU9uc2lRaUk2Ym5Wc2JDd2lRazlQVENJNmJuVnNiQ3dpUWxNaU9tNTFiR3dzSWt3aU9tNTFiR3dzSWswaU9tNTFiR3dzSWs0aU9tNTFiR3dzSWs1VElqcHVkV3hzTENKT1ZVeE1JanB1ZFd4c0xDSlRJam9pTURBd01EQXdNekF6TWpVeU1UUTNMVEl3TWpFdE1ERXRNVFJVTWpNNk1UYzZOVEJhTFRBd01EQXdNRFUzTXpVd05EZ3hPU0lzSWxOVElqcHVkV3hzZlN3aVNYUmxiVTkzYm1WeVNVUWlPbnNpUWlJNmJuVnNiQ3dpUWs5UFRDSTZiblZzYkN3aVFsTWlPbTUxYkd3c0lrd2lPbTUxYkd3c0lrMGlPbTUxYkd3c0lrNGlPbTUxYkd3c0lrNVRJanB1ZFd4c0xDSk9WVXhNSWpwdWRXeHNMQ0pUSWpvaU56TXpNamN6TmpjaUxDSlRVeUk2Ym5Wc2JIMHNJa2wwWlcxUGQyNWxja2xFTFVsMFpXMUpSQzFUZEdGeWRITkJkQzFKUkNJNmV5SkNJanB1ZFd4c0xDSkNUMDlNSWpwdWRXeHNMQ0pDVXlJNmJuVnNiQ3dpVENJNmJuVnNiQ3dpVFNJNmJuVnNiQ3dpVGlJNmJuVnNiQ3dpVGxNaU9tNTFiR3dzSWs1VlRFd2lPbTUxYkd3c0lsTWlPaUl3TURBd01EQXdOek16TWpjek5qY3RNREF3TURBd016QXpNalV5TVRRM0xUSXdNakV0TURFdE1UUlVNak02TVRjNk5UQmFMVEF3TURBd01EVTNNelV3TkRneE9TSXNJbE5USWpwdWRXeHNmU3dpVDNkdVpYSkpSQ0k2ZXlKQ0lqcHVkV3hzTENKQ1QwOU1JanB1ZFd4c0xDSkNVeUk2Ym5Wc2JDd2lUQ0k2Ym5Wc2JDd2lUU0k2Ym5Wc2JDd2lUaUk2Ym5Wc2JDd2lUbE1pT201MWJHd3NJazVWVEV3aU9tNTFiR3dzSWxNaU9pSTNNek15TnpNMk55SXNJbE5USWpwdWRXeHNmWDA9In19"},"points":2}', '{"array_join":", ","max_items":3}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (278, 9, 'subscribers_count', '[[[subscribers_count]]]', 'subscribers_count', 'integer', 'Count', 'Template tag for Count. Numeric value.', '4', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (281, 5, 'subscribers_latest_broadcaster_id', '[[[subscribers_latest_broadcaster_id]]]', 'subscribers.data.0.broadcaster_id', 'string', 'Latest Broadcaster Id', 'Broadcaster ID', '"73327367"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (282, 5, 'subscribers_latest_broadcaster_login', '[[[subscribers_latest_broadcaster_login]]]', 'subscribers.data.0.broadcaster_login', 'string', 'Latest Broadcaster Login', 'Broadcaster login', '"jasperdiscovers"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (283, 5, 'subscribers_latest_broadcaster_name', '[[[subscribers_latest_broadcaster_name]]]', 'subscribers.data.0.broadcaster_name', 'string', 'Latest Broadcaster Name', 'Broadcaster name', '"JasperDiscovers"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (284, 5, 'subscribers_latest_gifter_id', '[[[subscribers_latest_gifter_id]]]', 'subscribers.data.0.gifter_id', 'string', 'Latest Gifter Id', 'Gift giver ID (if applicable)', '""', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (285, 5, 'subscribers_latest_gifter_login', '[[[subscribers_latest_gifter_login]]]', 'subscribers.data.0.gifter_login', 'string', 'Latest Gifter Login', 'Gift giver login (if applicable)', '""', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (286, 5, 'subscribers_latest_gifter_name', '[[[subscribers_latest_gifter_name]]]', 'subscribers.data.0.gifter_name', 'string', 'Latest Gifter Name', 'Gift giver name (if applicable)', '""', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (287, 5, 'subscribers_latest_is_gift', '[[[subscribers_latest_is_gift]]]', 'subscribers.data.0.is_gift', 'boolean', 'Latest Is Gift', 'Whether subscription is a gift', 'false', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (288, 5, 'subscribers_latest_plan_name', '[[[subscribers_latest_plan_name]]]', 'subscribers.data.0.plan_name', 'string', 'Latest Plan Name', 'Subscription plan name', '"Tier 1 Sub"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (289, 5, 'subscribers_latest_tier', '[[[subscribers_latest_tier]]]', 'subscribers.data.0.tier', 'string', 'Latest Tier', 'Subscription tier', '"1000"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (290, 5, 'subscribers_latest_user_id', '[[[subscribers_latest_user_id]]]', 'subscribers.data.0.user_id', 'string', 'Latest User Id', 'Latest subscriber user ID', '"97930127"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (291, 5, 'subscribers_latest_user_name', '[[[subscribers_latest_user_name]]]', 'subscribers.data.0.user_name', 'string', 'Latest User Name', 'Latest subscriber name', '"f1los2"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (292, 5, 'subscribers_latest_user_login', '[[[subscribers_latest_user_login]]]', 'subscribers.data.0.user_login', 'string', 'Latest User Login', 'Latest subscriber login', '"f1los2"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (321, 5, 'subscribers_pagination_cursor', '[[[subscribers_pagination_cursor]]]', 'subscribers.pagination.cursor', 'string', 'Pagination Cursor', 'Template tag for Pagination Cursor. Text value.', '"eyJiIjp7IkN1cnNvciI6ImV5SkpkR1Z0U1VRdFUzUmhjblJ6UVhRdFNVUWlPbnNpUWlJNmJuVnNiQ3dpUWs5UFRDSTZiblZzYkN3aVFsTWlPbTUxYkd3c0lrd2lPbTUxYkd3c0lrMGlPbTUxYkd3c0lrNGlPbTUxYkd3c0lrNVRJanB1ZFd4c0xDSk9WVXhNSWpwdWRXeHNMQ0pUSWpvaU1EQXdNREF3TXpBek1qVXlNVFExTFRJd01qRXRNRGt0TURsVU1UZzZNemM2TXpkYUxURjRkV3d6Y1hWVU1HZHFUR3BIZVhkS1IzaEdiVVJwWTJWWGFTSXNJbE5USWpwdWRXeHNmU3dpU1hSbGJVOTNibVZ5U1VRaU9uc2lRaUk2Ym5Wc2JDd2lRazlQVENJNmJuVnNiQ3dpUWxNaU9tNTFiR3dzSWt3aU9tNTFiR3dzSWswaU9tNTFiR3dzSWs0aU9tNTFiR3dzSWs1VElqcHVkV3hzTENKT1ZVeE1JanB1ZFd4c0xDSlRJam9pTnpNek1qY3pOamNpTENKVFV5STZiblZzYkgwc0lrbDBaVzFQZDI1bGNrbEVMVWwwWlcxSlJDMVRkR0Z5ZEhOQmRDMUpSQ0k2ZXlKQ0lqcHVkV3hzTENKQ1QwOU1JanB1ZFd4c0xDSkNVeUk2Ym5Wc2JDd2lUQ0k2Ym5Wc2JDd2lUU0k2Ym5Wc2JDd2lUaUk2Ym5Wc2JDd2lUbE1pT201MWJHd3NJazVWVEV3aU9tNTFiR3dzSWxNaU9pSXdNREF3TURBd056TXpNamN6TmpjdE1EQXdNREF3TXpBek1qVXlNVFExTFRJd01qRXRNRGt0TURsVU1UZzZNemM2TXpkYUxURjRkV3d6Y1hWVU1HZHFUR3BIZVhkS1IzaEdiVVJwWTJWWGFTSXNJbE5USWpwdWRXeHNmU3dpVDNkdVpYSkpSQ0k2ZXlKQ0lqcHVkV3hzTENKQ1QwOU1JanB1ZFd4c0xDSkNVeUk2Ym5Wc2JDd2lUQ0k2Ym5Wc2JDd2lUU0k2Ym5Wc2JDd2lUaUk2Ym5Wc2JDd2lUbE1pT201MWJHd3NJazVWVEV3aU9tNTFiR3dzSWxNaU9pSTVOemt6TURFeU55SXNJbE5USWpwdWRXeHNmWDA9In0sImEiOnsiQ3Vyc29yIjoiZXlKSmRHVnRTVVF0VTNSaGNuUnpRWFF0U1VRaU9uc2lRaUk2Ym5Wc2JDd2lRazlQVENJNmJuVnNiQ3dpUWxNaU9tNTFiR3dzSWt3aU9tNTFiR3dzSWswaU9tNTFiR3dzSWs0aU9tNTFiR3dzSWs1VElqcHVkV3hzTENKT1ZVeE1JanB1ZFd4c0xDSlRJam9pTURBd01EQXdNekF6TWpVeU1UUTNMVEl3TWpFdE1ERXRNVFJVTWpNNk1UYzZOVEJhTFRBd01EQXdNRFUzTXpVd05EZ3hPU0lzSWxOVElqcHVkV3hzZlN3aVNYUmxiVTkzYm1WeVNVUWlPbnNpUWlJNmJuVnNiQ3dpUWs5UFRDSTZiblZzYkN3aVFsTWlPbTUxYkd3c0lrd2lPbTUxYkd3c0lrMGlPbTUxYkd3c0lrNGlPbTUxYkd3c0lrNVRJanB1ZFd4c0xDSk9WVXhNSWpwdWRXeHNMQ0pUSWpvaU56TXpNamN6TmpjaUxDSlRVeUk2Ym5Wc2JIMHNJa2wwWlcxUGQyNWxja2xFTFVsMFpXMUpSQzFUZEdGeWRITkJkQzFKUkNJNmV5SkNJanB1ZFd4c0xDSkNUMDlNSWpwdWRXeHNMQ0pDVXlJNmJuVnNiQ3dpVENJNmJuVnNiQ3dpVFNJNmJuVnNiQ3dpVGlJNmJuVnNiQ3dpVGxNaU9tNTFiR3dzSWs1VlRFd2lPbTUxYkd3c0lsTWlPaUl3TURBd01EQXdOek16TWpjek5qY3RNREF3TURBd016QXpNalV5TVRRM0xUSXdNakV0TURFdE1UUlVNak02TVRjNk5UQmFMVEF3TURBd01EVTNNelV3TkRneE9TSXNJbE5USWpwdWRXeHNmU3dpVDNkdVpYSkpSQ0k2ZXlKQ0lqcHVkV3hzTENKQ1QwOU1JanB1ZFd4c0xDSkNVeUk2Ym5Wc2JDd2lUQ0k2Ym5Wc2JDd2lUU0k2Ym5Wc2JDd2lUaUk2Ym5Wc2JDd2lUbE1pT201MWJHd3NJazVWVEV3aU9tNTFiR3dzSWxNaU9pSTNNek15TnpNMk55SXNJbE5USWpwdWRXeHNmWDA9In19"', NULL, true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (322, 5, 'subscribers_points', '[[[subscribers_points]]]', 'subscribers.points', 'integer', 'Points', 'Subscriber points', '2', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (323, 5, 'subscribers_total', '[[[subscribers_total]]]', 'subscribers.total', 'integer', 'Total', 'Total number of subscribers', '2', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (324, 9, 'goals', '[[[goals]]]', 'goals', 'array', 'Goals', 'Template tag for Goals. List of values.', '{"data":[[]]}', '{"array_join":", ","max_items":3}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);
INSERT INTO public.template_tags (id, category_id, tag_name, display_tag, json_path, data_type, display_name, description, sample_data, formatting_options, is_active, created_at, updated_at, tag_type, version, is_editable, original_tag_name, user_id) VALUES (325, 9, 'goals_count', '[[[goals_count]]]', 'goals_count', 'integer', 'Count', 'Template tag for Count. Numeric value.', '1', '{"number_format":{"decimals":0,"thousands_separator":","}}', true, '2026-03-05 20:44:54', '2026-03-05 20:44:54', 'standard', '1.0', false, NULL, 50);

-- kits
INSERT INTO public.kits (id, owner_id, title, description, thumbnail, is_public, forked_from_id, fork_count, created_at, updated_at) VALUES (3, 50, 'Midnight Purple', 'This dark-toned template and alert overlay kit contains one toolbar and all alerts for the available events within Overlabels right now. Fork this Kit to get a decent start.', 'https://res.cloudinary.com/dy185omzf/image/upload/v1772747997/kits/thumbnails/jfpdwhuctfbgbioye00n.jpg', true, NULL, 0, '2026-03-05 22:03:22', '2026-03-05 22:03:29');

-- kit_templates
INSERT INTO public.kit_templates (id, kit_id, overlay_template_id, created_at, updated_at) VALUES (7, 3, 53, '2026-03-05 22:03:22', '2026-03-05 22:03:22');
INSERT INTO public.kit_templates (id, kit_id, overlay_template_id, created_at, updated_at) VALUES (8, 3, 54, '2026-03-05 22:03:22', '2026-03-05 22:03:22');
INSERT INTO public.kit_templates (id, kit_id, overlay_template_id, created_at, updated_at) VALUES (9, 3, 55, '2026-03-05 22:03:22', '2026-03-05 22:03:22');
INSERT INTO public.kit_templates (id, kit_id, overlay_template_id, created_at, updated_at) VALUES (10, 3, 56, '2026-03-05 22:03:22', '2026-03-05 22:03:22');
INSERT INTO public.kit_templates (id, kit_id, overlay_template_id, created_at, updated_at) VALUES (11, 3, 57, '2026-03-05 22:03:22', '2026-03-05 22:03:22');
INSERT INTO public.kit_templates (id, kit_id, overlay_template_id, created_at, updated_at) VALUES (12, 3, 58, '2026-03-05 22:03:22', '2026-03-05 22:03:22');
INSERT INTO public.kit_templates (id, kit_id, overlay_template_id, created_at, updated_at) VALUES (13, 3, 59, '2026-03-05 22:03:22', '2026-03-05 22:03:22');
INSERT INTO public.kit_templates (id, kit_id, overlay_template_id, created_at, updated_at) VALUES (14, 3, 60, '2026-03-05 22:03:22', '2026-03-05 22:03:22');

-- overlay_templates
INSERT INTO public.overlay_templates (id, slug, owner_id, name, description, html, css, js, is_public, version, fork_of_id, template_tags, metadata, view_count, fork_count, created_at, updated_at, type, head) VALUES (53, 'scary-sparkling-sun-half-dog', 50, 'Midnight Purple Toolbar', 'Midnight toned toolbar built with flexbox that stretches nicely with longer usernames.', '<div class="overlay-bar">
    <!-- Profile Section -->
    <div class="profile-section">
        <span class="stat-number">Hi, I''m</span>
          <span class="username">[[[c:myname]]]
          <img src="[[[c:avatar]]]" alt="" width="40" />
        </span>
    </div>
    <!-- Stats Section -->
    <div class="stat-item">    
        <span class="stat-number">[[[followers_total]]]</span>
        <span class="stat-label">Followers</span>
    </div>

    <div class="stat-item">
        <span class="stat-number">[[[subscribers_total]]]</span>
        <span class="stat-label">Subscribers</span>
    </div>
    <!-- Latest Section -->
    <div class="latest-section">
        <div class="latest-item">
            <span class="latest-label">Latest Sub</span>
            <span class="latest-value">[[[subscribers_latest_user_name]]]</span>
        </div>

        <div class="latest-item">
            <span class="latest-label">Latest Follower</span>
            <span class="latest-value">[[[followers_latest_user_name]]]</span>
        </div>
    </div>
</div>', '* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: ''ABeeZee'', sans-serif;
  font-size: 23px;
  background: #1a1625;
  min-height: 100vh;
  display: flex;
  align-items: end;
  justify-content: center;
  padding: 20px;
}
div {
  transition: all 300ms ease;
}
.overlay-bar {
  width: calc(100vw - 2rem);
  height: auto;
  border-radius: 24px;
  border: 2px solid rgba(255, 140, 90, 0.6);
  background: rgba(25, 20, 35, 0.8);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  padding: 2rem;
  gap: 2rem;
}

.profile-section {
  display: flex;
  align-items: center;
  gap: 15px;
}
.username {
  display: flex;
  flex-flow: row nowrap;
  align-content: center;
  align-items: center;
  justify-content: space-between;
}
.username img {
  border-radius: 100px;
  margin-left: .5rem;
}
.social-icons {
  background: #2b0346;
  border-radius: 14px;  
  padding: 10px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.social-icon {
  width: 24px;
  height: 24px;
  fill: #b600ff;
}

.username {
  color: #A97ACB;
  font-weight: 600;
  letter-spacing: -0.3px;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.stat-number {
  color: #B600FF;
  font-weight: 600;
  
}

.stat-label {
  color: #A97ACB;
}

.latest-section {
  display: flex;
  align-items: center;
  gap: 40px;
  margin-left: auto;
}

.latest-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.latest-label {
  color: #b794f6;
  font-weight: 600;
}

.latest-value {
  color: #e2d9ff;
}', NULL, true, 1, NULL, '["c:myname","c:avatar","followers_total","subscribers_total","subscribers_latest_user_name","followers_latest_user_name"]', NULL, 0, 0, '2026-03-05 21:35:18', '2026-03-05 21:35:18', 'static', '<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=abeezee:400" rel="stylesheet" />');
INSERT INTO public.overlay_templates (id, slug, owner_id, name, description, html, css, js, is_public, version, fork_of_id, template_tags, metadata, view_count, fork_count, created_at, updated_at, type, head) VALUES (54, 'smart-twinkling-star-half-moth', 50, 'Midnight Purple - New Follower Alert', 'This shows when you have a new follower on your Twitch channel.', '<div class="outer">
  <div class="inner">
    <div class="text-line-1"><strong>[[[event.user_name]]]</strong> just followed</div>
    <div class="text-line-2">Thank you for joining!</div>
  </div>
</div>', ':root {
  --color-start: 163, 92, 69;
  --color-text: #e2d9ff;
}', NULL, true, 1, NULL, '["event.user_name"]', NULL, 0, 0, '2026-03-05 21:37:46', '2026-03-05 21:37:46', 'alert', NULL);
INSERT INTO public.overlay_templates (id, slug, owner_id, name, description, html, css, js, is_public, version, fork_of_id, template_tags, metadata, view_count, fork_count, created_at, updated_at, type, head) VALUES (55, 'brave-playing-valley-sapphire-bear', 50, 'Midnight Purple - New Subscriber Alert', 'This shows when you have a new subscriber on your Twitch channel.', '<div class="outer">
  <div class="inner">
    <div class="text-line-1"><strong>[[[event.user_name]]]</strong> just subscribed @ tier [[[event.tier_display]]]!</div>
    <div class="text-line-2">Thank you so much for your support!</div>
  </div>
</div>', ':root {
  --color-start: 53, 29, 84;
  --color-text: #e2d9ff;
}', NULL, true, 1, NULL, '["event.user_name","event.tier_display"]', NULL, 0, 0, '2026-03-05 21:38:22', '2026-03-05 21:38:22', 'alert', NULL);
INSERT INTO public.overlay_templates (id, slug, owner_id, name, description, html, css, js, is_public, version, fork_of_id, template_tags, metadata, view_count, fork_count, created_at, updated_at, type, head) VALUES (56, 'fast-floating-garden-hidden-cat', 50, 'Midnight Purple - Resubscriber Alert', 'This shows when you have a resubscriber on your Twitch channel.', '<div class="outer">
  <div class="inner">
    <div class="text-line-1">[[[event.user_name]]] resubscribed @ tier [[[event.tier_display]]] for [[[event.cumulative_months]]] months, with a [[[event.streak_months]]] month[[[if:event.streak_months > 1]]]s[[[endif]]] streak!</div>
    <div class="text-line-2">[[[event.message.text]]]</div>
  </div>
</div>', ':root {
  --color-start: 21, 97, 109;
  --color-text: #ace0e8;
}', NULL, true, 1, NULL, '["event.user_name","event.tier_display","event.cumulative_months","event.streak_months","endif","event.message.text"]', NULL, 0, 0, '2026-03-05 21:40:26', '2026-03-05 21:40:26', 'alert', NULL);
INSERT INTO public.overlay_templates (id, slug, owner_id, name, description, html, css, js, is_public, version, fork_of_id, template_tags, metadata, view_count, fork_count, created_at, updated_at, type, head) VALUES (57, 'strange-rushing-window-wool-cheetah', 50, 'Midnight Purple - Channel Point Redemption Alert', 'This shows when a Channel Point Reward is redeemed on your Twitch channel.', '<div class="outer">
  <div class="inner">
    <div class="text-line-1">[[[event.user_name]]] redeemed [[[event.reward.title]]] for [[[event.reward.cost]]] channel points.</div>
    <div class="text-line-2">[[[event.reward.prompt]]][[[if:event.user_input]]]: [[[event.user_input]]][[[endif]]]</div>
  </div>
</div>', ':root {
  --color-start: 37, 21, 109;
  --color-text: #ace0e8;
}', NULL, true, 1, NULL, '["event.user_name","event.reward.title","event.reward.cost","event.reward.prompt","if:event.user_input","event.user_input","endif"]', NULL, 0, 0, '2026-03-05 21:40:56', '2026-03-05 21:40:56', 'alert', NULL);
INSERT INTO public.overlay_templates (id, slug, owner_id, name, description, html, css, js, is_public, version, fork_of_id, template_tags, metadata, view_count, fork_count, created_at, updated_at, type, head) VALUES (58, 'calm-discovering-valley-metal-fox', 50, 'Midnight Purple - Bits Cheer Alert', 'This shows when somebody cheers Bits in your Twitch channel.', '<div class="outer">
  <div class="inner">
    <div class="text-line-1">[[[event.user_name]]] cheered <strong>[[[event.bits]]] bits!</strong> Thank you so much!</div>
    <div class="text-line-2">&ldquo;[[[event.message]]]&rdquo;</div>
  </div>
</div>', ':root {
  --color-start: 81, 21, 109;
  --color-text: #d0c3d6;
}', NULL, true, 1, NULL, '["event.user_name","event.bits","event.message"]', NULL, 0, 0, '2026-03-05 21:41:28', '2026-03-05 21:41:28', 'alert', NULL);
INSERT INTO public.overlay_templates (id, slug, owner_id, name, description, html, css, js, is_public, version, fork_of_id, template_tags, metadata, view_count, fork_count, created_at, updated_at, type, head) VALUES (59, 'rough-tinkering-grass-twisted-bear', 50, 'Midnight Purple - Gift Subscription Alert', 'This shows when somebody gifts a subscription in your Twitch channel.', '<div class="outer">
  <div class="inner">
    <div class="text-line-1">[[[event.user_name]]] just gifted <strong>[[[event.total]]] tier [[[event.tier_display]]] sub[[[if:event.total > 1]]]s[[[endif]]]</strong> Thank you so much!</div>
    <div class="text-line-2">&hearts; They have gifted [[[event.cumulative_total]]] subs in the channel &hearts;</div>
  </div>
</div>', ':root {
  --color-start: 81, 21, 109;
  --color-text: #d0c3d6;
}', NULL, true, 1, NULL, '["event.user_name","event.total","event.tier_display","endif","event.cumulative_total"]', NULL, 0, 0, '2026-03-05 21:41:57', '2026-03-05 21:41:57', 'alert', NULL);
INSERT INTO public.overlay_templates (id, slug, owner_id, name, description, html, css, js, is_public, version, fork_of_id, template_tags, metadata, view_count, fork_count, created_at, updated_at, type, head) VALUES (60, 'big-painting-fire-leather-cheetah', 50, 'Midnight Purple - Raid Alert', 'This shows when another streamer raids your Twitch channel.', '<div class="outer">
  <div class="inner">
    <div class="text-line-1">[[[event.from_broadcaster_user_name]]] just raided us with <strong>[[[event.viewers]]] viewers!</strong> Thank you so much!</div>
    <div class="text-line-2">Welcome in raiders, enjoy the stream and thanks for sticking around!</div>
  </div>
</div>', ':root {
  --color-start: 64, 120, 59;
  --color-text: #d1e8cf;
}', NULL, true, 1, NULL, '["event.from_broadcaster_user_name","event.viewers"]', NULL, 0, 0, '2026-03-05 21:42:28', '2026-03-05 21:42:28', 'alert', NULL);

-- overlay_controls
INSERT INTO public.overlay_controls (id, overlay_template_id, user_id, key, label, type, value, config, sort_order, created_at, updated_at, source, source_managed) VALUES (14, 53, 50, 'myname', 'My Name', 'text', 'JasperDiscovers', NULL, 0, '2026-03-05 21:35:33', '2026-03-05 21:35:33', NULL, false);
INSERT INTO public.overlay_controls (id, overlay_template_id, user_id, key, label, type, value, config, sort_order, created_at, updated_at, source, source_managed) VALUES (15, 53, 50, 'avatar', 'My Avatar', 'text', 'https://static-cdn.jtvnw.net/jtv_user_pictures/1d0df896-13c2-4f15-b6ca-f7d40d2fffc5-profile_image-70x70.png', NULL, 1, '2026-03-05 21:35:47', '2026-03-05 21:35:47', NULL, false);

-- event_template_mappings
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (5, 50, 'channel.follow', 54, 5000, true, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'slide-bottom', 'slide-left');
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (6, 50, 'channel.subscribe', 55, 8000, true, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'slide-bottom', 'slide-left');
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (7, 50, 'channel.subscription.gift', 59, 8000, true, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'slide-bottom', 'slide-left');
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (8, 50, 'channel.subscription.message', 56, 8000, true, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'slide-bottom', 'slide-left');
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (9, 50, 'channel.cheer', 58, 8000, true, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'slide-bottom', 'slide-left');
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (10, 50, 'channel.raid', 60, 8000, true, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'slide-bottom', 'slide-left');
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (11, 50, 'channel.channel_points_custom_reward_redemption.add', 57, 5000, true, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'slide-bottom', 'slide-left');
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (12, 50, 'stream.online', NULL, 5000, false, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'fade', 'fade');
INSERT INTO public.event_template_mappings (id, user_id, event_type, template_id, duration_ms, enabled, settings, created_at, updated_at, transition_in, transition_out) VALUES (13, 50, 'stream.offline', NULL, 5000, false, NULL, '2026-03-05 21:44:42', '2026-03-05 21:44:42', 'fade', 'fade');

RESET session_replication_role;

-- Sequence resets
SELECT setval(pg_get_serial_sequence('public.users', 'id'), COALESCE((SELECT MAX(id) FROM public.users), 1));
SELECT setval(pg_get_serial_sequence('public.template_tag_categories', 'id'), COALESCE((SELECT MAX(id) FROM public.template_tag_categories), 1));
SELECT setval(pg_get_serial_sequence('public.template_tags', 'id'), COALESCE((SELECT MAX(id) FROM public.template_tags), 1));
SELECT setval(pg_get_serial_sequence('public.kits', 'id'), COALESCE((SELECT MAX(id) FROM public.kits), 1));
SELECT setval(pg_get_serial_sequence('public.kit_templates', 'id'), COALESCE((SELECT MAX(id) FROM public.kit_templates), 1));
SELECT setval(pg_get_serial_sequence('public.overlay_templates', 'id'), COALESCE((SELECT MAX(id) FROM public.overlay_templates), 1));
SELECT setval(pg_get_serial_sequence('public.overlay_controls', 'id'), COALESCE((SELECT MAX(id) FROM public.overlay_controls), 1));
SELECT setval(pg_get_serial_sequence('public.event_template_mappings', 'id'), COALESCE((SELECT MAX(id) FROM public.event_template_mappings), 1));

COMMIT;

bhaawp-em-realex
================

Events Manager to Realex Payment gateway

curl --proxy dub-proxy:8080 --proxy-user xx -i -X POST http://bhaa.ie/realex-ipn.php > realex.html

curl --proxy dub-proxy:8080 --proxy-user xx -i --data "TIMESTAMP=20131022092652&MERCHANT_ID=bhaa&ORDER_ID=0-20131022092652&ACCOUNT=internet&CURRENCY=EUR&CUST_NUM=0&COMMENT1=Booking2086620for20event20103&COMMENT2=You''''re20booking20for20event20Annual20Membership202013&AUTO_SETTLE_FLAG=1&MD5HASH=bc2d88813a0fb62d890a41955b789f03&AMOUNT=1500&guid=e6f19e43ae1643dd98594c24c57bc0b2&52454d4f54455f41444452=NjIuNDAuMzguMzg&626f6f6b696e675f6964=ODY2OjEwMzp0cnVl&756964=MA&7072696365=MTUwMA&VAR_REF=&PROD_ID=&pas_step=2&PAYMENTBUTTON=false&pas_cctype=VISA&pas_ccnum=xxxxxxxxxxxxxxxx&pas_cccvc=xxx&pas_cccvcind=1&pas_ccmonth=02&pas_ccyear=15&pas_ccname=x" -X POST http://bhaa.ie/realex-ipn.php > realex.html

#Options +FollowSymlinks
#RewriteEngine on

# 2 DAYS - http://www.askapache.com/htaccess/apache-speed-cache-control.html
<FilesMatch ".(ico|pdf|flv|jpg|jpeg|png|gif|js|css|swf)$">
Header set Cache-Control "max-age=172800, public"
</FilesMatch>

# http://stackoverflow.com/questions/12951357/wordpress-permalink-structure-change-issue
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^/realex-ipn\.php$ - [L]
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>

# END WordPress


http://superuser.com/questions/149329/what-is-the-curl-command-line-syntax-to-do-a-post-request

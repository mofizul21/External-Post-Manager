# External Post Manager API Route

It is a WordPress plugin for managing external posts. By using these API routes you will able to create, edit and delete a post in your WordPress website.

## How to use this plugin
1. Install WordPress local or live server.
2. From `wp-admin` install the plugin and activate.
3. In wp-config.php add the line `define('EXTERNAL_POST_MANAGER_API_KEY', 'mytestapikey125896ss');` Here `mytestapikey125896ss` you can replace with your preferred API KEY.
4. In the Postman app, create a new request and select POST method. In the Headers tab, add a new header with the key "Authorization" and the value "Bearer mytestapikey125896ss". See the below image.
![4u](https://github.com/user-attachments/assets/84432530-5274-44e3-a4e1-4508c35d56ee)


Now you are ready to use those plugin API routes. See below which API routes are available for it.

## Create post | Method: POST
`/wp-json/external-post-manager/v1/create-post`

In the Body tab, add the following JSON data and hit the Send button.
```
{
  "title": "It us a new Post",
  "content": "This new post content.",
  "status": "publish",
  "category": 1
}
```
![CreatePost](https://github.com/user-attachments/assets/0541d15c-8b44-4e70-b903-59a78b3ca897)


## Edit post | Method: POST
`/wp-json/external-post-manager/v1/edit-post`

In the Body tab, add the following JSON data with `id` and hit the Send button.
```
{
  "id": 136,
  "title": "Awesome post",
  "content": "This new post content.",
  "status": "publish",
  "category": 1
}
```
![Edit Post](https://github.com/user-attachments/assets/fe06dca7-3412-433c-99a8-fed248518568)



## Delete post | Mehod: DELETE
`/wp-json/external-post-manager/v1/delete-post`

In the Body tab, add the following JSON data with `id` only and hit the Send button.
```
{
    "id": 136
}
```
![DeletePost](https://github.com/user-attachments/assets/ff2750ab-b69e-465f-8f66-46ceacc665c0)


If you see any error, add this line to your .htaccess file.
```
<IfModule mod_setenvif.c>
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
</IfModule>
```

If you have any question, please contact me at [https://mofizul.com](Mofizul.Com) or Email me at [mofizul21@gmail.com](mofizul21@gmail.com).

Thank you.

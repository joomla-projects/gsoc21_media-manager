# GSoC 2021: Joomla! Media Manager

## Introduction
Using images in the content of a website is extremely important to help the visitors connect and feel at ease on the website. But what’s even more important is to 
optimize the images used in the content by properly sizing them for different screens and not using more than required. Otherwise, unoptimized images could increase the 
load time of the website unnecessarily and drive the users away.  

On the other hand, in web content, different media types must be used in different situations. 
So, users should be able to upload the media type of their choosing and perform needed operations on it without any problems before adding it to the web.

## Goals
- Implement responsive images with art direction: Deliver a feature that automatically adds a srcset attribute to any image once it gets added to website content.
- Set custom breakpoints in content editors: Implement a new feature that allows the content authors to select different sizes and breakpoints (small, medium, large, or custom) 
- for the inserted image. Once the new image gets inserted into the page, srcset gets generated based on the size options.
- Improve “Insert/Edit Image" form: Add new form controls to the form to allow the users to add/edit details of an image in the editor.
- Handle different media types: Add new features and plugins for different media types to make it possible for users to easily upload and perform more operations on them.

## Work done during the 10 weeks

### Week 1
**Tasks:** [#1](https://github.com/joomla-projects/gsoc21_media-manager/issues/1), [#3](https://github.com/joomla-projects/gsoc21_media-manager/issues/3),
[#4](https://github.com/joomla-projects/gsoc21_media-manager/issues/4)

The functions that I worked on this week contained the main source code for responsive image generation. So the main goal for this week was to implement the functions 
which get triggered whenever we click the “Save” button on any web content editing page where there’s an image inserted into the content.

![1_exyg_gIT0-p4XQWeDmbH-g](https://user-images.githubusercontent.com/62054743/130349404-16c5a9c2-4b2a-44cb-8793-05e528cb8cc3.gif)

**PR:** [#5](https://github.com/joomla-projects/gsoc21_media-manager/pull/5)

### Week 2
**Tasks:** [#6](https://github.com/joomla-projects/gsoc21_media-manager/issues/6), [#7](https://github.com/joomla-projects/gsoc21_media-manager/issues/7)

Images can get inserted to web content by using these two: content editors or forms. So I needed to create two different helper functions for handling the insertion: 
`generateResponsiveFormImages` and `generateResponsiveContentImages`.

**PR:** [#8](https://github.com/joomla-projects/gsoc21_media-manager/pull/8)

### Week 3
**Tasks:** [#9](https://github.com/joomla-projects/gsoc21_media-manager/issues/9)

In this week, I had to improve the image generation functionality implemented in week 2 by using a plugin which ensures that we keep the code **DRY** (Don’t Repeat Yourself): 
it’s written and used only once instead of getting duplicated in every single model where it’s needed. I used **onContentBeforeSave** and **onContentAfterSave** content events to 
implement this. Additionally, I implemented a new function that takes a content string as a parameter and inserts a srcset attribute (with a dummy value initially) to 
every single img tag there and returns the updated content.

**PR:** [#13](https://github.com/joomla-projects/gsoc21_media-manager/pull/13)

### Week 4
**Tasks:** [#12](https://github.com/joomla-projects/gsoc21_media-manager/issues/6), [#7](https://github.com/joomla-projects/gsoc21_media-manager/issues/12)

I didn’t set a lot of tasks that bring a new feature this week since I had already worked on most of the functionality that has to be done till the first evaluation. 
So what I really needed to do was to complete all the unfinished and unoptimized tasks from the previous weeks and to finally wrap up the responsive images with default 
sizes functionality. So the first thing that I did was to create reusable functions for generating **srcset and sizes** attributes for a given image.

![1_05G9pxoWcqFwkgBBfwIAJA](https://user-images.githubusercontent.com/62054743/130349708-9655a321-d371-4fb4-900e-e27191082bba.png)

**PR:** [#14](https://github.com/joomla-projects/gsoc21_media-manager/pull/14)

### Week 5
**Tasks:** [#15](https://github.com/joomla-projects/gsoc21_media-manager/issues/6), [#7](https://github.com/joomla-projects/gsoc21_media-manager/issues/15)

Over the previous weeks, I managed to add the functionality for generating responsive sized versions of an image and inserting srcset and sizes attributes to it. 
But what if someone wants to have the images to be generated in sizes that are different than ours? That’s why I added some additional options to make it fully customizable:
- **Add a new plugin option.** The plugin that’s in charge of the responsive images must also be the place where users can customize the default sizes. 
So I added a radio button and dynamic form controls so that content authors can choose the options which suit them best.
- **Add new options for form images.** Users can override the default sizes by using the plugin options: the sizes they specify become the new default, but what if they 
- want to set different sizes for each image separately? I added new form controls for individual form images to override the default plugin options.

![1_J1QtkMS3Sf9nw0SS_Z43ZA](https://user-images.githubusercontent.com/62054743/130349775-298bdcc8-ff12-4240-af84-3f60f7b9dadd.png)

**PR:** [#16](https://github.com/joomla-projects/gsoc21_media-manager/pull/16)

### Week 6
**Tasks:** [#17](https://github.com/joomla-projects/gsoc21_media-manager/issues/6), [#7](https://github.com/joomla-projects/gsoc21_media-manager/issues/17)

The main goal of this week was to basically improve the form which is used to edit images in content editors by adding new form controls. But what will these form 
controls do? Let’s try a scenario to understand that: 

I want to add an image to the content editor and when I add it, I specify some details like image class,  lazy loading option, and figure caption/class by using the form 
that appears on image insertion:

![1_xu92ommmhBqKoY142VCSOA](https://user-images.githubusercontent.com/62054743/130349812-ccd288ad-ef30-4242-81b5-bea6ace0bc0d.png)

Then I realize that I had a typo in the image class or I just want to change it to something else and I go to the image, right click on it and open the 
“Insert/Edit Image” dialog, but I can’t find the option to change it. Why? Because there isn’t any 😃. So this is the first problem
I tried to solve this week. The second is more of a feature than a problem: adding the option to customize responsive image sizes for each image.

![1_N-4KZfDwXXbufgP1UcsMBw](https://user-images.githubusercontent.com/62054743/130349883-b83526a4-a56d-480b-8556-f9a6bdc61c32.png)

**PR:** [#18](https://github.com/joomla-projects/gsoc21_media-manager/pull/18)

### Week 7
**Tasks:** [#19](https://github.com/joomla-projects/gsoc21_media-manager/issues/6), [#7](https://github.com/joomla-projects/gsoc21_media-manager/issues/7)

So during the previous weeks, I managed to add the Responsive Images with Art Direction functionality, and I thought that it was fully customizable until 
I got this feedback from the Joomla community in the comment section of my PR to the core:

![1_vNHlPdeweHgLy0zdGMQQog](https://user-images.githubusercontent.com/62054743/130349916-2d9a6cce-a195-46cd-ab20-1a41cc1c368f.png)

I realized that it wasn’t fully customizable since the users couldn’t change the creation method of images which can be either by resizing (default), cropping, 
or resizing then cropping. On the other hand, adding a new title field for image sizes would be a good idea for better organizing the image sizes. So I decided 
to implement this for plugin settings where users get to define the “new default”:

![1_fZrJiGo7nBlH6S09Ja7W0A](https://user-images.githubusercontent.com/62054743/130349959-766366dc-da06-4b21-b434-290dce634cf1.png)

**PR:** [#20](https://github.com/joomla-projects/gsoc21_media-manager/pull/20)

### Week 8
**Tasks:** [#21](https://github.com/joomla-projects/gsoc21_media-manager/issues/6), [#7](https://github.com/joomla-projects/gsoc21_media-manager/issues/21)

Well, I have to say that this week’s task was one of the most interesting tasks that I’ve done so far, I guess this is because I got to dive into the codebase of 
media manager which is developed with VueJS. One of the improvements was to add appropriate icons for video and audio files instead of a default file icon:

![1_HZu0rTMMSppaS-BX6pxyEQ](https://user-images.githubusercontent.com/62054743/130349989-30d5f567-6d92-423b-84d6-8b6434a5325d.png)

I used the [Media Element](https://github.com/mediaelement/mediaelement) plugin to improve the preview of playable media items:

![1_SXAfqSZ3M_jPSv0um2e0DQ](https://user-images.githubusercontent.com/62054743/130350006-6c7dead2-646d-4cb0-8da8-f4833625780c.png)

**PR:** [#22](https://github.com/joomla-projects/gsoc21_media-manager/pull/22)

### Week 9
**Tasks:** [#23](https://github.com/joomla-projects/gsoc21_media-manager/issues/6), [#7](https://github.com/joomla-projects/gsoc21_media-manager/issues/23)

Sometimes, responsive versions of an image become useless. Imagine this scenario: you insert an image to the article content and responsive images get generated, 
everything is perfect so far. Then you decide not to use this image anymore in your content or you replace it with another image or you delete this image from your 
media manager. In all these situations, responsive images that were generated previously become obsolete. So I implemented the functions that compare initial and 
final versions of web content and detect the images that are not being used anymore. Currently, an alert that contains the names of the images gets popped up, but 
I’m planning to implement a modal where user can select and remove these images with just a few clicks.

![1_yaix49v-bEQYfSBc8WkFiQ](https://user-images.githubusercontent.com/62054743/130350061-d0adb479-9592-4565-ba99-d0cc8c831acc.png)

**PR:** [#24](https://github.com/joomla-projects/gsoc21_media-manager/pull/24)

### Week 10
This week was mostly spent for working on the improvements of the existing features and functionalities implemented during the previous weeks and for 
finally creating the pull requests to the core. I also had the chance to present my project to a broader audience on the event called “Joomla NEXT” organized 
for the release of Joomla 4:

![1_Ztx60vWaBbkdus8M9yRqAA](https://user-images.githubusercontent.com/62054743/130350120-401ec143-0ebf-45d8-b8d6-0810f3685333.png)


## PRs to the core
- [Implement Responsive Images with Art Direction](https://github.com/joomla/joomla-cms/pull/34803)
- [Responsive Images final version and "Insert/Edit Image" form improvements](https://github.com/joomla/joomla-cms/pull/35250)
- [Add a player plugin to better preview audio and video files](https://github.com/joomla/joomla-cms/pull/35177)

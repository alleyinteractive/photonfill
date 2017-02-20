#PhotonFill

PhotonFill is a Developer focused WordPress plugin that allows for easy implementation of responsive images using [picturefill](https://github.com/scottjehl/picturefill) while integrating the powerful free CDN of [Jetpack Photon](https://jetpack.me/support/photon/). This plugin optionally allows the implementation of lazy loaded responsive images using [lazysizes](https://github.com/aFarkas/lazysizes).

##Installation

1. Download and install the [Jetpack Plugin](https://jetpack.me/). Photon must be active which requires an internet connection. For local development environments where Jetpack cannot connect your account and thus Photon cannot be active, PhotonFill also works with the [My Photon Plugin](https://github.com/alleyinteractive/my-photon) which allows you to run self hosted version of Photon.
2. PhotonFill should work out of the box with default default breakpoints and image sizes. It will attempt to guess the correct size images to serve from Photon based on intermediate images sizes that have been defined in your theme by ``add_image_size()`` and the default breakpoints. This of course was not the intended way for Photonfill to work and it's real power lies in the advanced configuration.

##Advanced Configuration

The power of PhotonFill is that it allows different mainpulations of a single image within a set of defined breakpoints.

###Intermediate Image Size File Creation
PhotonFill by default disables creation of subsizes of images when calling ``add_image_size()``. This saves a ton of space server side and can make future data migrations of a site's uploads directory substantially smaller. If for some reason you need to have the intermediate image sizes created physically on upload, you can re-enabled it with the filter hook ``photonfill_enable_resize_upload``.

###Defining Breakpoints
By default, PhotonFill defines the following breakpoints:
```
array(
	'mobile' => array( 'max' => 640 ),
	'mini-tablet' => array( 'min' => 640 ),
	'tablet' => array( 'min' => 800 ),
	'desktop' => array( 'min' => 1040 ),
	'hd-desktop' => array( 'min' => 1280 ),
	'all' => array( 'min' => 0 ),
)
```
You can set custom breakpoints using the filter hook ``photonfill_breakpoints``. This breakpoint array can take a number of parameters, but only a small subset of them are relevant as global breakpoint parameters. The global breakpoint array has the relevant paramters:

* ``unit`` (string) Will take a value of `em` or `px`. PhotonFill does not support `vw` multi units or the calc function.  Default (px)
* ``min`` (int) Min width of breakpoint. Takes an int.  Defaults are in px.
* ``max`` (int) Max width of a breakpoint.  Takes an int. Defaults are in px.

You should minimally define a min or a max. You can also define both. Customizing the default breakpoints might look like so:

```
/**
 * Define our custom breakpoints
 * @param array
 * @return array
 */
function mytheme_define_breakpoints( $breakpoints ) {
		return array(
			'xl'   => array( 'unit' => 'em', 'min' => 80 ),
			'l'    => array( 'unit' => 'em', 'min' => 65, 'max' => 80 ),
			'm'    => array( 'unit' => 'em', 'min' => 50, 'max' => 65 ),
			's-m'  => array( 'unit' => 'em', 'min' => 30, 'max' => 65 ),
			's'    => array( 'unit' => 'em', 'min' => 30, 'max' => 50 ),
			'xs-s' => array( 'unit' => 'em', 'max' => 50 ),
			'xs'   => array( 'unit' => 'em', 'max' => 30 ),
			'all'  => array( 'min' => 0 ),

		);
}
add_filter( 'photonfill_breakpoints', 'mytheme_define_breakpoints' );
```
You can specify any breakpoint name. No need to keep the device themed keys.

###Defining Image Sizes and the Image Stack
For already established themes that have used ``add_image_size()`` to create multiple crops of an image, PhotonFill will use these image sizes to create an image stack that will scale with the breakpoints defined above. Using the default breakpoints and the default WordPress core image sizes, the PhotonFill image stack would look like:
```
array(
	'thumbnail' => array(
		'mobile' => array( 'width' => 150, 'height' => 150 ),
		'mini-tablet' => array( 'width' => 150, 'height' => 150 ),
		'tablet' => array( 'width' => 150, 'height' => 150 ),
		'desktop' => array( 'width' => 150, 'height' => 150 ),
		'hd-desktop' => array( 'width' => 150, 'height' => 150 ),
	),
	'medium' => array(
		'mobile' => array( 'width' => 300, 'height' => 300 ),
		'mini-tablet' => array( 'width' => 300, 'height' => 300 ),
		'tablet' => array( 'width' => 300, 'height' => 300 ),
		'desktop' => array( 'width' => 300, 'height' => 300 ),
		'hd-desktop' => array( 'width' => 300, 'height' => 300 ),
	),
	'large' => array(
		'mobile' => array( 'width' => 640, 'height' => 640 ),
		'mini-tablet' => array( 'width' => 640, 'height' => 640 ),
		'tablet' => array( 'width' => 1024, 'height' => 1024 ),
		'desktop' => array( 'width' => 1024, 'height' => 1024 ),
		'hd-desktop' => array( 'width' => 1024, 'height' => 1024 ),
	),
	'post-thumbnail' => array(
		'mobile' => array( 'width' => 640, 'height' => 396 ),
		'mini-tablet' => array( 'width' => 640, 'height' => 396 ),
		'tablet' => array( 'width' => 825, 'height' => 510 ),
		'desktop' => array( 'width' => 825, 'height' => 510 ),
		'hd-desktop' => array( 'width' => 825, 'height' => 510 ),
	),
)

```

In new theme development with PhotonFill, there is no need call  ``add_image_size()`` at all. You can get better results with accuracy of responsive images if you define your own custom image stack using the ``photonfill_image_sizes`` filter hook. There are a large number of parameters that can be taken into the image stack breakpoint array. The arguments that can be passed are:

* ``width`` (int) Width in px of the photon image that will be served at that breakpoint. Will guess if empty.
* ``height`` (int) Height in px of the photon image that will be served at that breakpoint. Will guess if empty.
* ``quality`` (int) Image quality percent. Photon uses 90 for jpgs and 80 for pngs
* ``crop`` (boolean) Default is true. Will perform cropping on images.
* ``default`` (boolean) Use this breakpoint image as the fall back default for the img element
* ``pixel-density`` (int) Takes either 1 or 2. Defaults to 2. Will return higher rez images for 2.
* ``callback`` (string) Will use this function to transform photon args. Defaults to `PhotonFill_Transform::center_crop()`

You can do a lot more if you customize your image stack.  You can eliminate unused breakpoints for image sizes, you can perform breakpoint specific image transformations, set defaults and quality level. Using our custom breakpoints defined above, we could implement a custom image stack like so:

```
/**
 * Set the image stack of sizes and breakpoints
 */
function mytheme_set_image_stack( $image_stack ) {
	return array(
		'featured-full' => array(
			'xl'  => array( 'width' => 1920, 'height' => 560, 'default' => true, 'callback' => 'top_down_crop' ),
			'l'   => array( 'width' => 1040, 'height' => 500, 'callback' => 'top_down_crop' ),
			's-m' => array( 'width' => 800, 'height' => 450, 'callback' => 'top_down_crop' ),
			'xs'  => array( 'width' => 480, 'height' => 270, 'callback' => 'top_down_crop' ),
		),
		'featured-large' => array(
			'xl' => array( 'width' => 1260, 'height' => 550, 'default' => true, 'callback' => 'top_down_crop' ),
			'l'  => array( 'width' => 1260, 'height' => 550, 'callback' => 'top_down_crop' ),
			'm'  => array( 'width' => 1260, 'height' => 550, 'callback' => 'top_down_crop' ),
			's'  => array( 'width' => 800, 'height' => 450, 'callback' => 'top_down_crop' ),
			'xs' => array( 'width' => 480, 'height' => 270, 'callback' => 'top_down_crop' ),
		),
		'featured-medium' => array(
			'xl' => array( 'width' => 620, 'height' => 349, 'default' => true ),
			'l'  => array( 'width' => 620, 'height' => 349 ),
			'm'  => array( 'width' => 620, 'height' => 349 ),
			's'  => array( 'width' => 800, 'height' => 450 ),
			'xs' => array( 'width' => 480, 'height' => 270 ),
		),
		'featured-thumb' => array(
			'xl' => array( 'width' => 400, 'height' => 225, 'default' => true ),
			'l'  => array( 'width' => 400, 'height' => 225 ),
			'm'  => array( 'width' => 400, 'height' => 225 ),
			's'  => array( 'width' => 800, 'height' => 450 ),
			'xs' => array( 'width' => 480, 'height' => 270 ),
		),
	);
}
add_filter( 'photonfill_image_sizes', 'mytheme_set_image_stack' );
```

##Photon Transforms and Callbacks
Photon uses an url parameter based [API](https://developer.wordpress.com/docs/photon/api/) for manipulation of images. The PhotonFill Transform class has a basic set of transforms and defaults to the center_crop transform.  You can alter the default crop by using the ``photonfill_default_transform`` filter hook. For anything that can't be handled with PhotonFill's transform methods, you can create your own transform easily by specifying a callback in the image stack. It will first check to see if a theme function exists of that callback name. If not it will then check the PhotonFill Transform class methods. Finally if no callback is found, it will return the PhotonFill default transform. PhotonFill hooks into the Photon process early and sets custom args that are then parsed right before the CDN url is generated. In this new process, you can expect to find these parameters available to you when the callback is run, but not part of the final CDN url that generated. Your callbacks should return a new array of Photon args instead of appending to the args passed to the callback. This will help avoid the generation of invalid CDN Urls

* ``attachment_id`` (int) The attachment id of the image
* ``crop`` (boolean) Should the image be cropped.
* ``image_size`` (string) The image size of the current attachment. Uses 'full', if a height/width array.
* ``breakpoint`` (string) Breakpoint of current CDN Url being generated
* ``width`` (int) Width in px of breakpoint image
* ``height`` (int) Height in px of breakpoint image
* ``quality`` (int) Percentage of image quality

## Lazy Loading Responsive Images
PhotonFill also has the option of lazy loading responsive images and allowing the browser to guess which image is most appropriate to display accoring to the clients browser window size and density. It uses [lazysizes.js](https://github.com/aFarkas/lazysizes) as the polyfill.  You can enable it through the ``photonfill_use_lazyload`` filter hook.

## Disable Photonfill in the admin area
Sometimes photonfill doesn't play well with another plugin that shows images in the admin area, in this case, it might be useful to disable Photonfill for the admin area. Use the filter hook ``photonfill_use_in_admin`` and return ``false`` to disable it. By default, photonfill will be turned on in the admin area.

## Availiable Filter Hooks
* ``photonfill_use_lazyload`` Enable/Disable lazy loaded responsive images using lazysizes.js.  Default (disabled)
* ``photonfill_use_in_admin`` Enable/Disable photonfill in the admin area (dashboard).  Default (enabled)
* ``photonfill_breakpoints`` Set custom breakpoints.
* ``photonfill_base_unit_pixel`` If unit specified in breakpoints is `em` what base pixel is the theme. Default (16)
* ``photonfill_image_sizes`` An image stack of image sizes for corresponding breakpoints.
* ``photonfill_enable_resize_upload`` Enable/Disable generation of intermediate image sizes. Default (disabled)
* ``photonfill_picture_class`` Modify the class with a picture element.
* ``photonfill_default_transform`` Set the default transformation for Photon. Default (`Photonfill_Transform::center_crop()`)
* ``photonfill_use_picture_as_default`` Use a picture element as the default when calling `the_post_thumbnail` or `wp_get_attachment_image`
* ``photonfill_bypass_image_downsize`` Bypass the image downsize hooks which can be problematic on some environments. Set `true` if all of your image URLs are returning the original URL.

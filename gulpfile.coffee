gulp = require 'gulp'
sass = require 'gulp-sass'
uglify = require "gulp-uglify"
coffee = require "gulp-coffee"
browserify = require 'gulp-browserify'
rename = require 'gulp-rename'
extReplace = require "gulp-ext-replace"
autoprefixer = require "gulp-autoprefixer"
livereload = require "gulp-livereload"
uglifycss = require "gulp-uglifycss"
watch = require "gulp-watch"
changed = require "gulp-changed"
imagemin = require 'gulp-imagemin' # 图片压缩
pngquant = require 'imagemin-pngquant' # 深度压缩

logError = (er) ->
    console.log(er)
    this.emit("end")

##默认启动的服务列表
gulp.task 'default',["script", 'coffee', 'sass', 'image', 'watch']

##编译scss文件
gulp.task 'sass', ->
    gulp.src ["public/v2/src/scss/**/*.scss", "public/v2/src/scss/**/*.css"]
    .pipe(changed("public/v2/assets/css", {extension: ".min.css"}))
    .pipe(sass())
    .on "error", logError
    .pipe autoprefixer
        browsers: ['last 3 versions'],
        cascade: true, #是否美化属性值 默认：true 像这样：
    #-webkit-transform: rotate(45deg);
    #        transform: rotate(45deg);
        remove:true #是否去掉不必要的前缀 默认：true
    #.pipe uglifycss()
    .pipe rename suffix: '.min'
    .pipe gulp.dest 'public/v2/assets/css'
    .pipe livereload()

##映射 img 文件
gulp.task "image", ->
    gulp.src "public/v2/src/img/**/*.{png,jpg,jpeg,gif,bmp,svg}"
        .pipe(changed("public/v2/assets/img"))
        .pipe imagemin
            progressive: true, # 无损压缩JPG图片
            svgoPlugins: [{removeViewBox: false}], # 不移除svg的viewbox属性
            use: [pngquant()] # 使用pngquant插件进行深度压缩
        .on "error", logError
        .pipe gulp.dest "public/v2/assets/img"
        .pipe livereload()


#编译coffee文件
gulp.task "coffee", ->
    gulp.src "public/v2/src/js/**/*.coffee", read:false
    .pipe(changed("public/v2/assets/js", {extension: ".min.js"}))
    .pipe browserify
        debug: true
        transform: ['coffeeify']
        extensions: ['.coffee']
    .on "error", logError
    #.pipe uglify()
    .pipe rename suffix: ".min"
    .pipe extReplace '.js'
    .pipe gulp.dest "public/v2/assets/js"
    .pipe livereload()



#编译原生js文件
gulp.task "script", ->
    gulp.src "public/v2/src/js/**/*.js"
    #.pipe uglify()
    .pipe rename suffix: ".min"
    .pipe gulp.dest "public/v2/assets/js"
    .pipe livereload()

#监控修改
gulp.task "watch", ->
    livereload.listen()     #实时刷新
    gulp.watch "public/v2/src/scss/**/*.scss", ['sass']
    gulp.watch "public/v2/src/scss/**/*.css", ['sass']
    gulp.watch "public/v2/src/js/**/*.js", ['script']
    gulp.watch "public/v2/src/js/**/*.coffee", ['coffee']
    gulp.watch "public/v2/src/img/**/*.{png,jpg,jpeg,gif,bmp,svg}", ['image']

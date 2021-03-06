<?
/**
 * Media transform output for video thumbnails
 *
 * @ingroup Media
 */

class ThumbnailVideo extends ThumbnailImage {

//	function ThumbnailVideo( $file, $url, $width, $height, $path = false, $page = false ){
//
//		$this->file = $file;
//
//		/*
//		 * Get thumbnail url
//		 */
//		$oImageServing = new ImageServing( null, $width, $height );
//		$this->url = $oImageServing->getUrl( $file, $file->getWidth(), $file->getHeight() );
//
//		# These should be integers when they get here.
//		# If not, there's a bug somewhere.  But let's at
//		# least produce valid HTML code regardless.
//		$this->width = round( $width );
//		$this->height = round( $height );
//		$this->path = $path;
//		$this->page = $page;
//	}

	function getFile() {
		return $this->file;
	}

	function getUrl() {
		return $this->url;
	}

	function getPath() {
		return $this->path;
	}

	function getPage() {
		return $this->page;
	}

	function getWidth() {
		return $this->width;
	}

	function getHeight() {
		return $this->height;
	}

	/*
	 * Render video thumbnail as image thumbnail
	 */
	function renderAsThumbnailImage($options) {

		$thumb = F::build(
			'ThumbnailImage',
			array(
				"file" => $this->getFile(),
				"url" => $this->getUrl(),
				"width" => $this->getWidth(),
				"height" => $this->getHeight(),
				"path" => $this->getPath(),
				"page" => $this->getPage()
			)
		); /** @var $thumb ThumbnailImage  */

		// make sure to replace 'image' css class whith 'video' css class
		// in order to make thumbnail be handled correctly by RTE
		if ( isset( $options['img-class'] ) ) {

			$imgClasses = explode( ' ', $options['img-class'] );
			$changeIndex = array_search( 'image', $imgClasses );

			if ( $changeIndex !== false ) {
				$imgClasses[$changeIndex] = "video";
			} else {
				$imgClasses[] = "video";
			}

			$options['img-class'] = implode( ' ', $imgClasses );
		} else {

			$options['img-class'] = "video";
		}

		return $thumb->toHtml( array('img-class' => $options['img-class']) );
	}

	/**
	 * @param array $options
	 * @return mixed|string
	 * @throws MWException
	 */
	function toHtml( $options = array() ) {
		if ( count( func_get_args() ) == 2 ) {
			throw new MWException( __METHOD__ .' called in the old style' );
		}

		if ( !empty( F::app()->wg->RTEParserEnabled ) ) {
			return $this->renderAsThumbnailImage($options);
		}

		wfProfileIn( __METHOD__ );

		$alt = empty( $options['alt'] ) ? '' : $options['alt'];

		/*
		 * in order to disable RDF metadata in video thumbnails
		 * pass disableRDF parameter to toHtml method
		 */
		$useRDFData = ( !empty( $options['disableRDF'] ) && $options['disableRDF'] == true ) ? false : true;


		/**
		 * Note: if title is empty and alt is not, make the title empty, don't
		 * use alt; only use alt if title is not set
		 * wikia change, Inez
		 */
		$title = !isset( $options['title'] ) ? $alt : $options['title'];

		$query = empty( $options['desc-query'] )  ? '' : $options['desc-query'];

		if ( !empty( $options['custom-url-link'] ) ) {
			$linkAttribs = array( 'href' => $options['custom-url-link'] );
			if ( !empty( $options['title'] ) ) {
				$linkAttribs['title'] = $options['title'];
			}
		} elseif ( !empty( $options['custom-title-link'] ) ) {
			$title = $options['custom-title-link'];
			$linkAttribs = array(
				'href' => $title->getLinkUrl(),
				'title' => empty( $options['title'] ) ? $title->getFullText() : $options['title']
			);
		} elseif ( !empty( $options['desc-link'] ) ) {
			$linkAttribs = $this->getDescLinkAttribs( empty( $options['title'] ) ? null : $options['title'], $query );
			if ( F::app()->checkSkin( array( 'oasis', 'wikiamobile' ) ) ) {
				$linkAttribs['data-video-name'] = $this->file->getTitle()->getText();
				$linkAttribs['href'] = $this->file->getTitle()->getLocalURL();
				if ( !empty( $options['id'] ) ){
					$linkAttribs['id'] = $options['id'];
				}
			}
		} elseif ( !empty( $options['file-link'] ) ) {
			$linkAttribs = array( 'href' => $this->file->getTitle()->getLocalURL() );
		} else {
			$linkAttribs = array();
		}

		if ( isset( $options['linkAttribs'] ) && is_array( $options['linkAttribs'] ) ) {
			$linkAttribs = array_merge( $linkAttribs, $options['linkAttribs'] );
		}

		$linkAttribs['class'] = empty($linkAttribs['class']) ? 'video' : $linkAttribs['class'].' video';

		if ( $useRDFData ) { // bugId: #46621
			$linkAttribs['itemprop'] = 'video';
			$linkAttribs['itemscope'] = '';
			$linkAttribs['itemtype'] = 'http://schema.org/VideoObject';
		}

		$attribs = array(
			'alt' => $alt,
			'src' => !empty($options['src']) ? $options['src'] : $this->url,
			'width' => $this->width,
			'height' => $this->height,
			'data-video' => $this->file->getTitle()->getText(),
		);

		if ( $useRDFData ) {
			$attribs['itemprop'] = 'thumbnail';
		}

	        if ( !empty($options['usePreloading']) ) {
	            $attribs['data-src'] = $this->url;
	        }

		if ( $this->file instanceof OldLocalFile ) {
			$archive_name = $this->file->getArchiveName();
			if ( !empty( $archive_name ) ) {
				$linkAttribs['href'] .= '?t='.$this->file->getTimestamp();
				$linkAttribs['data-timestamp'] = $this->file->getTimestamp();
			}
		}

		if ( !empty( $options['valign'] ) ) {
			$attribs['style'] = "vertical-align: {$options['valign']}";
		}
		$attribs['class'] = 'Wikia-video-thumb';
		if ( !empty( $options['img-class'] ) ) {
			$attribs['class'] .= ' ' . $options['img-class'];
		}

		if ($this->file instanceof WikiaLocalFile || $this->file instanceof WikiaForeignDBFile) {
			$extraBorder = $this->file->addExtraBorder( $this->width );
		}
		if ( !empty( $extraBorder ) ) {
			if ( !isset( $attribs['style'] ) ) $attribs['style'] = '';
			$attribs['style'] .= 'border-top: 15px solid black; border-bottom: '.$extraBorder.'px solid black;';
		}

		if ( isset( $options['imgExtraStyle'] ) ) {
			if ( !isset( $attribs['style'] ) ) $attribs['style'] = '';
			$attribs['style'] .= $options['imgExtraStyle'];
		}

		if ( isset( $options['duration'] ) && $options['duration'] == true ) {
			$duration = $this->file->getHandler()->getFormattedDuration();
		}

		if ( isset($options['constHeight']) ) {

			$this->appendHtmlCrop($linkAttribs, $options);
		}

		$html = ( $linkAttribs && isset($linkAttribs['href']) ) ? Xml::openElement( 'a', $linkAttribs ) : '';

		if ( isset( $duration ) && !empty( $duration ) ) {
			$timerProp = array( 'class'=>'timer' );
			if ( $useRDFData ) {
				$timerProp['itemprop'] = 'duration';
			}
			$html .= Xml::element( 'div', $timerProp,  $duration );
		}
		$playButtonHeight =  ( isset( $options['constHeight'] ) && $this->height > $options['constHeight'] ) ? $options['constHeight'] : $this->height;
		if ( !empty( $extraBorder ) ) $playButtonHeight += ( $extraBorder*2 );
		$html .= WikiaFileHelper::videoPlayButtonOverlay( $this->width, $playButtonHeight );
		$html .= Xml::element( 'img', $attribs, '', true );
		
		
		if( empty( $options['hideOverlay'] ) ) {
			$html .= WikiaFileHelper::videoInfoOverlay( $this->width, $this->file->getTitle() );
		}
		
		$html .= ( $linkAttribs && isset($linkAttribs['href']) ) ? Xml::closeElement( 'a' ) : '';

		//give extensions a chance to modify the markup
		wfRunHooks( 'ThumbnailVideoHTML', array( $options, $linkAttribs, $attribs, $this->file,  &$html ) );
		wfProfileOut( __METHOD__ );

		return $html;
	}

	private function appendHtmlCrop( &$linkAttribs, $options) {

		if ( !isset( $linkAttribs['style'] ) ) $linkAttribs['style'] = '';

		$linkAttribs['style'] .= 'overlay:hidden;';

		if ( $this->height <= $options['constHeight'] ) {

			$linkAttribs['style'] .= "height:{$this->height}px;";
			$linkAttribs['style'] .= 'margin-bottom:'.( $options['constHeight'] - $this->height )."px;";
			$linkAttribs['style'] .= 'padding-top:'.floor( ($options['constHeight'] - $this->height)/2 )."px;";

		} else {

			$linkAttribs['style'] .= "height:{$options['constHeight']}px;";
		}
	}
}

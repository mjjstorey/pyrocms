<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Pages Plugin
 *
 * Create links and whatnot.
 *
 * @package		PyroCMS
 * @author		PyroCMS Dev Team
 * @copyright	Copyright (c) 2008 - 2011, PyroCMS
 *
 */
class Plugin_Pages extends Plugin
{
	/**
	 * Get a page's URL
	 *
	 * @param int $id The ID of the page
	 * @return string
	 */
	function url()
	{
		$id		= $this->attribute('id');
		$page	= $this->pyrocache->model('pages_m', 'get', array($id));

		return site_url($page ? $page->uri : '');
	}
	
	/**
	 * Get a page by ID or slug
	 *
	 * @param int 		$id The ID of the page
	 * @param string 	$slug The uri of the page
	 * @return array
	 */
	function display()
	{
		return $this->db->select('pages.*, revisions.*')
					->where('pages.id', $this->attribute('id'))
					->or_where('pages.slug', $this->attribute('slug'))
					->where('status', 'live')
					->join('revisions', 'pages.revision_id = revisions.id', 'LEFT')
					->get('pages')
					->row_array();
	}
	
	/**
	 * Children list
	 *
	 * Creates a list of child pages
	 *
	 * Usage:
	 * {pyro:pages:children id="1" limit="5"}
	 *	<h2>{title}</h2>
	 *	    {body}
	 * {/pyro:pages:children}
	 *
	 * @param	array
	 * @return	array
	 */
	
	function children()
	{
		$limit = $this->attribute('limit');
		
		return $this->db->select('pages.*, revisions.*')
			->where('pages.parent_id', $this->attribute('id'))
			->where('status', 'live')
			->join('revisions', 'pages.revision_id = revisions.id', 'LEFT')
			->limit($limit)
			->get('pages')
			->result_array();
	}

	// --------------------------------------------------------------------------

	/**
	 * Page tree function
	 *
	 * Creates a nested ul of child pages
	 *
	 * Usage:
	 * {pyro:pages:page_tree start_id="5"}
	 *
	 * @param	array
	 * @return	array
	 */
	public function page_tree()
	{
		$start_id = $this->attribute('start_id');
		$this->ul_id = $this->attribute('ul_id', 'menu');
		$disable_levels = $this->attribute('disable_levels');
		
		// We take in disabled fields via pipe separated
		// strings. Now we explode 'em.
		
		$this->disable = explode("|", $disable_levels);
		
		// Get the URIs so we don't have to keep querying the
		// DB later
		
		$pages = $this->db->select('id, uri')->get('pages')->result();
		
		$this->uris = array();
		
		foreach ($pages as $uri)
		{
			$this->uris[$uri->id] = $uri->uri;
		}
		
		// Set the level, start the level & start the party
		
		$this->level = 1;
			
		$this->html = '';
		
		$this->_build_page_tree($start_id);
		
		return $this->html;
	}

	public function is()
	{
		$children_id	= $this->attribute('children');
		$descendent_id	= $this->attribute('descendent');
		$parent_id		= $this->attribute('parent');

		if ($children_id && $descendent_id)
		{
			if ( ! is_numeric($children_id))
			{
				$children_id = ($children = $this->pages_m->get_by(array('slug' => $children_id))) ? $children->id: 0;
			}

			if ( ! is_numeric($descendent_id))
			{
				$descendent_id = ($descendent = $this->pages_m->get_by(array('slug' => $descendent_id))) ? $descendent->id: 0;
			}

			if ( ! ($children_id && $descendent_id))
			{
				return FALSE;
			}

			$descendent_ids	= $this->pages_m->get_descendant_ids($descendent_id);

			return in_array($children_id, $descendent_ids);
		}

		if ($children_id && $parent_id)
		{
			if ( ! is_numeric($children_id))
			{
				$parent_id = ($parent = $this->pages_m->get_by(array('slug' => $parent_id))) ? $parent->id: 0;
			}

			return $parent_id ? (int) $this->pages_m->count_by(array(
				(is_numeric($children) ? 'id' : 'slug') => $children,
				'parent_id'	=> $parent_id
			)) > 0: FALSE;
		}
	}

	// --------------------------------------------------------------------------

	/**
	 * Recursive page tree function 
	 *
	 * @access	private
	 * @param	int
	 * @return	string
	 */
	private function _build_page_tree( $parent_id )
	{
		$this->db->where('status', 'live');
		$pages = $this->pages_m->get_many_by('parent_id', $parent_id);
		
		//Unset the parent
		foreach( $pages as $key => $page )
		{
			if( $page->id == $parent_id): unset( $pages[$key] ); endif;
		}
		
		if ( ! empty($pages) ):
				
			$this->html .= "\n".$this->_level_tabs( $this->level )."<ul>\n";
					
			foreach ($pages as $page):
							
				'/'.$this->uris[$page->id] == $this->uri->uri_string() ? $attr = 'class="current"' : $attr = '';
			
				$this->html .= $this->_level_tabs( $this->level+1 ).'<li>';
			
				if( !in_array($this->level, $this->disable) ):
				
					$this->html .= anchor($this->uris[$page->id], $page->title, $attr);
				
				else:
				
					$this->html .= $page->title;
				
				endif;				
				
				if( $page->has_children = $this->pages_m->has_children($page->id) ):
				
					$this->level++;
				
					$this->_build_page_tree( $page->id );
					
					$this->level--;
					
					$this->html .= $this->_level_tabs( $this->level+1 ).'</li>'."\n";
				
				else:
				
					$this->html .= '</li>'."\n";
				
				endif;
			
			endforeach;

			return $this->html .= $this->_level_tabs( $this->level ).'</ul>'."\n";
		
		endif;
	}

	// --------------------------------------------------------------------------

	/**
	 * Create tabs based on level
	 *
	 * Just to make the code look good
	 *
	 * @access	private
	 * @param	int
	 * @return	string
	 */
	private function _level_tabs( $level = 1 )
	{
		$tabs = '';
		
		$tab_count = 1;
		
		while( $tab_count < $level ):
		
			$tabs .= "\t";
			
			$tab_count++;
			
		endwhile;
		
		return $tabs;
	}

}

/* End of file plugin.php */